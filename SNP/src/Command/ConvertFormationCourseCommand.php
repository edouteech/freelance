<?php

namespace App\Command;


use App\Entity\FormationCourse;

use App\Entity\FormationParticipant;
use App\Repository\FormationCourseRepository;
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

class ConvertFormationCourseCommand extends AbstractCommand {

	/** @var EudonetAction $eudonet */
	private $eudonet;
	private $mailer;
	private $encryptor;
	private $servicesAction;
	private $zoomConnector;


	public function __construct (ContainerInterface $container, EudonetAction $eudonet, Mailer $mailer, ServicesAction $servicesAction, ZoomConnector $zoomConnector, LoggerInterface $logger, Encryptor $encryptor) {

		parent::__construct($container, $logger);

		$this->encryptor = $encryptor;
		$this->eudonet = $eudonet;
		$this->mailer = $mailer;
		$this->zoomConnector = $zoomConnector;
		$this->servicesAction = $servicesAction;
	}

	/**
	 * Configure
	 */
	protected function configure () {

		$this->setName('app:formation:convert');
		$this->setDescription("Convert formation course");
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

		/** @var FormationCourseRepository $formationCourseRepository */
		$formationCourseRepository = $this->entityManager->getRepository(FormationCourse::class);

		$formationCourses = $formationCourseRepository->findBy(['hasEdit'=>1]);

		foreach ($formationCourses as $formationCourse){

			if( $formationCourse->getFormat() == 'webinar' ){

				if( !$formationCourse->getWebinarId() ){

					$this->servicesAction->createWebinar($formationCourse);
					$formationCourse = $formationCourseRepository->find($formationCourse->getId());
				}
				else{

					$this->alertInstructors($formationCourse);
				}

				$this->alertParticipants($formationCourse);

				$this->output->writeln("<info>Formation edited</info>");
			}

			$formationCourse->setHasEdit(false);
			$this->eudonet->push($formationCourse);
		}

		return 1;
	}


	/**
	 * @param FormationCourse $formationCourse
	 * @return void
	 *
	 * @throws Exception
	 */
	private function alertInstructors($formationCourse)
	{
		$room = $formationCourse->getInstructor1();
		$formation = $formationCourse->getFormation();

		if( $room ){

			if( !$email = $room->getEmail() ){

				$this->error('Room '.$room->getId().' has no email address', false);
				return;
			}

			if( !$zoomUser = $this->zoomConnector->getUser($email) ){

				$this->error('Room '.$room->getId().' does not exist', false);
				return;
			}

			$zoomUserPassword = $zoomUser ? $zoomUser['password'] : '';
			$zoomPanelists = $this->zoomConnector->getWebinarPanelists($formationCourse->getWebinarId());

			foreach ($formationCourse->getInstructors() as $instructor) {

				if(!$instructor )
					continue;

				if( !$email = $instructor->getEmail() ){

					$this->error('Instructor '.$instructor->getFirstname().' '.$instructor->getLastname().' has no email address', false);
					return;
				}

				$zoomPanelist = ($zoomPanelists[$email]??false);

				$body = $this->mailer->createBodyMail('e-learning/zoom.html.twig',['formationCourse'=>$formationCourse, 'formation'=>$formation, 'room'=>$room, 'instructor'=>$instructor, 'room_password'=>$zoomUserPassword, 'panelist'=>$zoomPanelist]);
				$this->mailer->sendMessage($email, 'Votre formation a été modifiée !', $body, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);

				$this->output->writeln("<info>Instructor ".$instructor->getLastname()." revived : ".$email."</info>");
			}
		}
	}


	/**
	 * @param FormationCourse $formationCourse
	 * @throws Exception
	 */
	public function alertParticipants($formationCourse){

		if( $formationCourse->getWebinarId() )
			$registrants = $this->zoomConnector->getWebinarRegistrants($formationCourse->getWebinarId());
		else
			$registrants = [];

		$participants = $formationCourse->getParticipants();
		$formation = $formationCourse->getFormation();

		foreach ($participants as $participant){

			if( !$participant->getRegistered() )
				continue;

			$contact = $participant->getContact();
			$address = $participant->getAddress();

			if( $address && $email = $address->getEmail() ){

				$company = $address->getCompany();

				if( !$participant->getRegistrantId() ){

					$encryptedFormationParticipantId = urlencode(base64_encode($this->encryptor->encrypt($participant->getId())));
					$bodyMail = $this->mailer->createBodyMail('e-learning/edited.html.twig', ['title'=>'Votre formation a été modifiée !', 'company'=>$company, 'participantId'=>$encryptedFormationParticipantId, 'contact'=>$contact, 'formationCourse'=>$formationCourse, 'formation'=>$formation]);
				}
				else{

					if( !$registrant = ($registrants[$email]??false)){

						$this->error("formation participant ".$participant->getId()." email has changed", false);
						continue;
					}

					$bodyMail = $this->mailer->createBodyMail('e-learning/webinar.html.twig', ['title'=>'Votre formation a été modifiée !', 'company'=>$company, 'registrant'=>$registrant, 'contact' => $contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'formationParticipant'=>$participant]);
				}

				$this->mailer->sendMessage($email, "Modifications relatives à votre formation", $bodyMail, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);
				$this->output->writeln("<info>Email sent</info>");
			}
			else{

				$this->error("formation participant ".$participant->getId()." as no address or email", false);
			}
		}
	}
}