<?php

namespace App\Command;

use App\Entity\Formation;
use App\Entity\FormationCourse;
use App\Entity\FormationParticipant;
use App\Entity\Signatory;
use App\Repository\FormationCourseRepository;
use App\Repository\FormationParticipantRepository;
use App\Repository\SignatoryRepository;
use App\Service\EudonetAction;
use App\Service\Mailer;
use App\Service\ServicesAction;
use App\Service\ZoomConnector;
use Combodo\DoctrineEncryptBundle\Services\Encryptor;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class AlertFormationPariticipantsCommand extends AbstractCommand {

	/** @var EudonetAction $eudonet */
	private $eudonet;
	private $mailer;
	private $encryptor;
	private $servicesAction;
	private $zoomConnector;


	public function __construct (ContainerInterface $container, EudonetAction $eudonet, ServicesAction $servicesAction, Mailer $mailer, LoggerInterface $logger, Encryptor $encryptor, ZoomConnector $zoomConnector) {

		parent::__construct($container, $logger);

		$this->zoomConnector = $zoomConnector;
		$this->servicesAction = $servicesAction;
		$this->encryptor = $encryptor;
		$this->eudonet = $eudonet;
		$this->mailer = $mailer;
    }

	/**
	 * Configure
	 */
	protected function configure () {

		$this->setName('app:formation:alert');
		$this->setDescription("Alert unregistered formation participants");
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return bool
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	public function execute (InputInterface $input, OutputInterface $output) {

		$this->output = $output;

		/** @var FormationParticipantRepository $formationParticipantRepository */
		$formationParticipantRepository = $this->entityManager->getRepository(FormationParticipant::class);

		$this->alertUnrevivedParticipants(
			$formationParticipantRepository->getUnregistered()
		);

		/** @var FormationCourseRepository $formationCourseRepository */
		$formationCourseRepository = $this->entityManager->getRepository(FormationCourse::class);

		$this->resendInstructorsMail(
			$formationCourseRepository->getResendMail(Formation::FORMAT_WEBINAR)
		);

		$this->resendParticipantsMail(
			$formationParticipantRepository->getResendMail([Formation::FORMAT_WEBINAR, Formation::FORMAT_INSTRUCTOR_LED])
		);

		if( intval(date("H")) >= 8 && intval(date("H")) <= 9 ){

            $this->alertNextDayFormation(
                $formationCourseRepository->getUnreminded([Formation::FORMAT_WEBINAR, Formation::FORMAT_INSTRUCTOR_LED])
            );
        }

		$today = new \DateTime();
		$today->setTime(0, 0);

		$this->alertSignatories(
			$formationCourseRepository->findBy(['endAt'=>$today])
		);

		return 1;
	}


    /**
     * @param FormationParticipant[] $formationParticipants
     * @return void
     *
     * @throws Exception|ExceptionInterface
     */
	private function alertUnrevivedParticipants($formationParticipants)
	{
		$toUpdate = [];

		$in5days = new \DateTime();
		$in5days->modify('+5 days')->setTime(0, 0);

		foreach ($formationParticipants as $formationParticipant) {

			$address = $formationParticipant->getAddress();
            $contact = $formationParticipant->getContact();

			if( $address && $email = $address->getEmail() ){

				$formationCourse = $formationParticipant->getFormationCourse();
				$formation = $formationCourse->getFormation();

				if( $formationCourse->getEditNote() ){

					if( !$formationParticipant->getRevived() && $formationCourse->getStartAt() <= $in5days ){

						$encryptedFormationParticipantId = urlencode(base64_encode($this->encryptor->encrypt($formationParticipant->getId())));

						$bodyMail = $this->mailer->createBodyMail('e-learning/alert.html.twig', ['title'=>'Votre formation a été modifiée !', 'participantId'=>$encryptedFormationParticipantId, 'contact'=>$contact, 'formationCourse'=>$formationCourse, 'formation'=>$formation]);
						$this->mailer->sendMessage($email, 'RELANCE - Modifications relatives à votre formation', $bodyMail, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);

						$this->output->writeln("<info>".$contact->getLastname()." revived</info>");

						$formationParticipant->setRevived(true);
						$toUpdate[] = $formationParticipant;
					}
				}
				else{

					$company = $address->getCompany();

                    if( $formationCourse->getFormat() === 'webinar' && !$formationCourse->getWebinarId() )
                        $this->servicesAction->createWebinar($formationCourse);

					try {

                        $this->servicesAction->registerForWebinar($formationCourse, $contact, $company);
                        $this->output->writeln("<info>".$contact->getLastname()." registered</info>");

                    } catch (\Throwable $t) {

                        $this->mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Une erreur est survenue lors de la relance des inscriptions', 'Webinar :'.$formationCourse->getWebinarId().',  Participant '.$contact->getLastname().' '.$contact->getFirstname().' : '.$t->getMessage());
                        $this->output->writeln("<info>Alert email sent</info>");

                        $this->error($t->getMessage(), false);
                    }
				}
			}
			else{

                $this->mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Une erreur est survenue lors de la relance des inscriptions', "Participant ".$contact->getLastname().' '.$contact->getFirstname()." has no address or email");
                $this->error("formation participant ".$formationParticipant." has no address or email", false);
			}
		}

		foreach ($toUpdate as $item){

            try {

                $this->eudonet->push($item);

            } catch (\Throwable $t) {

                $this->error($t->getMessage(), false);
            }
        }
    }


    /**
     * @param FormationCourse $formationCourse
     * @return void
     *
     * @throws Exception|ExceptionInterface
     */
    private function alertInstructors($formationCourse)
    {
        $formation = $formationCourse->getFormation();

        if( $formationCourse->getFormat() == $formation::FORMAT_WEBINAR ){

            $room = $formationCourse->getInstructor1();

            if( $room ){

                if( !$email = $room->getEmail() ){

                    $this->error('Room '.$room->getId().' has no email address', false);
                    return;
                }

                if( !$zoomUser = $this->zoomConnector->getUser($email) ){

                    $this->error('Instructor '.$room->getId().' does not exist', false);
                    return;
                }

                $zoomUserPassword = $zoomUser ? $zoomUser['password'] : '';
                $zoomPanelists = $this->zoomConnector->getWebinarPanelists($formationCourse->getWebinarId());

                foreach ($formationCourse->getInstructors() as $instructor) {

                    if( $email = $instructor->getEmail() ){

                        $zoomPanelist = ($zoomPanelists[$email]??false);

                        $body = $this->mailer->createBodyMail('e-learning/zoom.html.twig',['formationCourse'=>$formationCourse, 'formation'=>$formation, 'room'=>$room, 'instructor'=>$instructor, 'room_password'=>$zoomUserPassword, 'panelist'=>$zoomPanelist]);
                        $this->mailer->sendMessage($email, 'Rappel : '.$_ENV['ZOOM_DEFAULT_CONTACT_NAME'].' vous invite à animer un webinaire Zoom', $body, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);

                        $this->output->writeln("<info>Instructor ".$instructor->getLastname()." reminded : ".$email."</info>");
                    }
                }
            }
        }
        elseif( $formationCourse->getFormat() == $formation::FORMAT_INSTRUCTOR_LED ){

            $instructors = $formationCourse->getInstructors();

            foreach ($instructors as $instructor){

                if( $email = $instructor->getEmail() ){

                    $bodyMail = $this->mailer->createBodyMail('formation/instructor-led-reminder.html.twig', ['title'=>'Rappel de votre formation', 'contact'=>$instructor, 'formation'=>$formation, 'formationCourse'=>$formationCourse]);
                    $this->mailer->sendMessage($email, 'Rappel de votre formation', $bodyMail, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);

                    $this->output->writeln("<info>Instructor ".$instructor->getLastname()." reminded : ".$email."</info>");
                }
            }
        }
    }


	/**
	 * @param FormationCourse[] $formationCourses
	 * @return void
	 *
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	private function alertNextDayFormation($formationCourses)
	{
		foreach ($formationCourses as $formationCourse) {

			$this->alertParticipants($formationCourse);
			$this->alertInstructors($formationCourse);

			$formationCourse->setReminded(true);
			$this->eudonet->push($formationCourse);
		}
	}


	/**
	 * @param FormationCourse[] $formationCourses
	 * @return void
	 *
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	private function resendInstructorsMail($formationCourses)
	{
		foreach ($formationCourses as $formationCourse) {

			$this->alertInstructors($formationCourse);

			$formationCourse->setResendMail(false);
			$this->eudonet->push($formationCourse);
		}
	}


	/**
	 * @param FormationParticipant[] $formationParticipants
	 * @return void
	 *
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	private function resendParticipantsMail($formationParticipants)
	{
		foreach ($formationParticipants as $formationParticipant) {

            $this->alertParticipant($formationParticipant);

            $formationParticipant->setResendMail(false);
			$this->eudonet->push($formationParticipant);
		}
	}


	/**
	 * @param FormationCourse $formationCourse
	 * @return void
	 *
	 * @throws Exception
	 */
	private function alertParticipants($formationCourse)
	{
		$formationParticipants = $formationCourse->getParticipants();

		foreach ($formationParticipants as $formationParticipant){

            $this->alertParticipant($formationParticipant);
        }
	}

    /**
     * @param FormationParticipant $formationParticipant
     * @throws Exception|ExceptionInterface
     */
    private function alertParticipant($formationParticipant)
    {
        if( $formationParticipant->getRegistered() ) {

            $address = $formationParticipant->getAddress();

            if( $address && $email = $address->getEmail() ){

                $company = $address->getCompany();

                $formationCourse = $formationParticipant->getFormationCourse();
                $formation = $formationCourse->getFormation();

                $contact = $formationParticipant->getContact();

                if( $formationCourse->getFormat() == $formation::FORMAT_WEBINAR ){

                    if( !$formationParticipant->getRegistrantId() )
                        return;

                    $registrants = $this->zoomConnector->getWebinarRegistrants($formationCourse->getWebinarId());

                    if( !$registrant = ($registrants[$email]??false)){

                        $this->mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Email participant invalide', 'Nous avons détecté un changement d\'email pour un des participants à la formation #'.$formationCourse->getWebinarId().', '.$email.'. Cet email n\'est pas enregistré sur Zoom pourtant le participant est inscrit.' );
                        $this->error("formation participant ".$formationParticipant->getId()." email has changed", false);

                        return;
                    }

                    $bodyMail = $this->mailer->createBodyMail('e-learning/webinar-reminder.html.twig', ['title'=>'Rappel de votre formation', 'registrant'=>$registrant, 'company'=>$company, 'contact'=>$contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'formationParticipant'=>$formationParticipant]);

                    $this->mailer->sendMessage($email, 'Rappel de votre formation', $bodyMail, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);
                    $this->output->writeln("<info>".$contact->getLastname()." reminded</info>");
                }
                elseif( $formationCourse->getFormat() == $formation::FORMAT_INSTRUCTOR_LED ){

                    if( $formationFoad = $formation->getFoad() )
                        $documents = $formationFoad->getDocuments();
                    else
                        $documents = [];

                    $bodyMail = $this->mailer->createBodyMail('formation/instructor-led-reminder.html.twig', ['title'=>'Rappel de votre formation', 'company'=>$company, 'contact'=>$contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'formationParticipant'=>$formationParticipant]);

                    $this->mailer->sendMessage($email, 'Rappel de votre formation', $bodyMail, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], $documents);
                    $this->output->writeln("<info>".$contact->getLastname()." reminded</info>");
                }

            } else {

                $this->error("formation participant ".$formationParticipant." as no address or email", false);
            }
        }
    }

    /**
     * @param $formationCourses
     * @throws Exception
     */
    private function alertSignatories($formationCourses)
	{
		$now = new \DateTime();

		if( intval($now->format('H')) == 20 || intval($now->format('H')) == 22 ){

			$today = new \DateTime();
			$today->setTime(0, 0);

			/** @var SignatoryRepository $signatoryRepository */
			$signatoryRepository = $this->entityManager->getRepository(Signatory::class);

			foreach ($formationCourses as $formationCourse){

				$signatories = $signatoryRepository->findAllByEntity(FormationCourse::class, $formationCourse->getId());

				$instructor = $formationCourse->getInstructor2();
				$formation = $formationCourse->getFormation();

				foreach ($signatories as $signatory){

					$address = $signatory->getAddress();

					if( $signatory->getStatus() != 'signed' && $address && $address->getEmail() ){

						$contact = $address->getContact();
						$isInstructor = $instructor && $instructor->getEmail() == $address->getEmail();

						$body = $this->mailer->createBodyMail('e-learning/unsigned.html.twig', ['formationCourse'=>$formationCourse, 'formation'=>$formation, 'isInstructor'=>$isInstructor]);
						$this->mailer->sendMessage($address->getEmail(), "Vous n'avez pas signé la feuille de présence" , $body, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);

						$this->output->writeln("<info>Participant ".$contact->getLastname()." revived</info>");
					}
				}
			}
		}
	}
}