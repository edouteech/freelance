<?php

namespace App\Service;

use DateTime;
use Doctrine\Common\Util\ClassUtils;
use Exception;
use App\Entity\Address;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Appendix;
use App\Entity\Contract;
use App\Entity\Signatory;
use App\Entity\Signature;
use Psr\Log\LoggerInterface;
use App\Entity\FormationFoad;
use App\Entity\AbstractEntity;
use Doctrine\ORM\ORMException;
use App\Entity\FormationCourse;
use App\Entity\EudoEntityMetadata;
use App\Entity\FormationParticipant;
use App\Repository\AppendixRepository;
use App\Repository\ContractRepository;
use App\Repository\SignatoryRepository;
use App\Repository\SignatureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

use Symfony\Component\Filesystem\Filesystem;
use App\Repository\FormationCourseRepository;
use App\Entity\FormationParticipantConnection;
use App\Repository\EudoEntityMetadataRepository;
use Symfony\Component\HttpKernel\KernelInterface;
use App\Repository\FormationParticipantRepository;

use Symfony\Component\Security\Core\User\UserInterface;
use App\Repository\FormationParticipantConnectionRepository;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Service\Mailer;

class ServicesAction extends AbstractService {

    private $eudonetAction;
    private $eudonetConnector;
    private $entityManager;
    private $elearningConnector;
    private $mailer;
    private $zoomConnector;
    private $contraliaAction;
    private $logger;
    private $kernel;


    /**
     * Services constructor.
     * @param CaciService $caciService
     * @param KernelInterface $kernel
     * @param EudonetAction $eudonetAction
     * @param EudonetConnector $eudonetConnector
     * @param EntityManagerInterface $entityManager
     * @param ContraliaAction $contraliaAction
     * @param ElearningConnector $elearningConnector
     * @param Mailer $mailer
     * @param ZoomConnector $zoomConnector
     * @param LoggerInterface $logger
     */
    public function __construct(CaciService $caciService, KernelInterface $kernel, EudonetAction $eudonetAction,  EudonetConnector $eudonetConnector, EntityManagerInterface $entityManager, ContraliaAction $contraliaAction, ElearningConnector $elearningConnector, Mailer $mailer, ZoomConnector $zoomConnector, LoggerInterface $logger){

        $this->eudonetAction = $eudonetAction;
        $this->eudonetConnector = $eudonetConnector;
        $this->elearningConnector = $elearningConnector;
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->zoomConnector = $zoomConnector;
        $this->contraliaAction = $contraliaAction;
        $this->caciService = $caciService;
        $this->logger = $logger;
        $this->kernel = $kernel;
    }

    /**
     * @param Contact $contact
     * @param Company|null $company
     * @param bool $force
     * @return void
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function registerForElearning(Contact &$contact, ?Company $company, $force=false){

        if( $contact->getElearningEmail() && $contact->getElearningToken() && !$force )
            return;

        $address = $contact->getAddress($company);

        if( !$address )
            throw new Exception('Address is required for ['.$contact->__toString().']');

        if( !$email = $address->getEmail() )
            throw new Exception('Email is required for ['.$contact->__toString().']');

        if( $contact->getElearningToken() && !$contact->getElearningEmail() && !$force ){

            $contact->setElearningEmail($email);
            $this->eudonetAction->push($contact);

            return;
        }

        $data = $this->elearningConnector->createUser([
                'email'=>$email,
                'firstname'=>$contact->getFirstname(),
                'lastname'=>$contact->getLastname()]
        );

        $contact->setElearningEmail($email);
        $contact->setElearningPassword($data['passwd']);
        $contact->setElearningToken($data['token']);

        $this->eudonetAction->push($contact);

        $bodyMail = $this->mailer->createBodyMail('e-learning/account.html.twig', ['title'=>"Formation à distance", 'contact' => $contact]);
        $this->mailer->sendMessage($email, 'Formation à distance', $bodyMail);
    }

    /**
     * @param FormationCourse $formationCourse
     * @param Contact $contact
     * @param Company|null $company
     * @return array|false
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function registerForWebinar(FormationCourse $formationCourse, Contact $contact, ?Company $company){

        if( !$formationCourse->getWebinarId() )
            throw new Exception('Webinar id is missing');

        if( !$formation = $formationCourse->getFormation() )
            throw new Exception('Formation not found');

        if( !$email = $contact->getEmail($company) )
            throw new Exception('Email is required for contact '.$contact->getId());

        /** @var FormationParticipantRepository $formationParticipantRepository */
        $formationParticipantRepository = $this->entityManager->getRepository(FormationParticipant::class );

        if(! $formationParticipant = $formationParticipantRepository->findOneBy(['contact'=>$contact, 'formationCourse'=>$formationCourse]) )
            throw new Exception('Formation participant not found');

        $registrantParams = [
            'email'=> $email,
            'first_name'=> $contact->getFirstname(),
            'last_name'=> $contact->getLastname(),
            'name'=> $contact->getFirstname() . ' ' . $contact->getLastname()
        ];

        if( $formationParticipant->getRegistrantId() )
            return $registrantParams;

        $registrants = $this->zoomConnector->getWebinarRegistrants($formationCourse->getWebinarId());

        if( $registrants[$email]??false )
            return $registrantParams;

        $registrant = $this->zoomConnector->addWebinarRegistrant($formationCourse->getWebinarId(), false, $registrantParams);
        $this->zoomConnector->updateWebinarRegistrantsStatus($formationCourse->getWebinarId(), false, 'approve', [['id'=>$registrant['registrant_id'], 'email'=>$email]]);
        $this->zoomConnector->addWebinarPanelist($formationCourse->getWebinarId(), $registrantParams);

        $formationParticipant->setRegistrantId($registrant['registrant_id']);
        $this->eudonetAction->push($formationParticipant);

        $bodyMail = $this->mailer->createBodyMail('e-learning/webinar.html.twig', ['title'=>"Confirmation d'inscription - Webinar live", 'company'=>$company, 'contact' => $contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'formationParticipant'=>$formationParticipant, 'registrant'=>$registrant]);
        $this->mailer->sendMessage($email, "Confirmation d'inscription - Webinar live", $bodyMail, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);

        return $registrantParams;
    }

    /**
     * @param FormationCourse $formationCourse
     * @return string|null
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function createWebinar(FormationCourse &$formationCourse){

        if( $formationCourse->getWebinarId() )
            return $formationCourse->getWebinarId();

        if( !$formation = $formationCourse->getFormation() )
            throw new Exception('Formation not found');

        $zoomUserId = $_ENV['ZOOM_CLIENT_ID'];
        $zoomUser = false;

        if( $room = $formationCourse->getInstructor1() ){

            if( !$email = $room->getEmail() )
                throw new Exception('Room '.$room->getId().' has no email address');

            if( !$zoomUser = $this->zoomConnector->getUser($email) )
                throw new Exception('Room '.$room->getId().' does not exist');

            $zoomUserId = $zoomUser['id'];
        }

        $duration = $formationCourse->getHours()*60/$formationCourse->getDays();
        $recurrence = null;

        $params = [
            'topic'=> $formation->getTitle(),
            'agenda'=> $formation->getObjective(),
            'duration'=> $duration,
            'recurrence'=> $recurrence,
            'start_time'=> $formationCourse->getStartAt(true)->format('c')
        ];

        if( $formationCourse->getDays() > 1 ){

            $params['type'] = 9;

            $params['recurrence'] = [
                'type'=> 1,
                'end_times'=> $formationCourse->getDays()
            ];
        }

        $panelists = [];

        foreach ($formationCourse->getInstructors() as $instructor) {

            if( !$instructor->getEmail() ){

                $this->mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'],'Instructor '.$instructor->getFirstname().' '.$instructor->getLastname().' has no email address');
                continue;
            }

            $panelists[] = [
                'name' => $instructor->getLastname() . ' ' . $instructor->getFirstname(),
                'email' => $instructor->getEmail()
            ];
        }

        if( empty($panelists) )
            throw new Exception('No panelist found');

        $webinar = $this->zoomConnector->createWebinar($zoomUserId, $params);
        $formationCourse->setWebinarId($webinar['id']);

        $this->eudonetAction->push($formationCourse);

        if( $formationCourse->getDays() > 1 ){

            $eudoEntityMetadataRepository = $this->entityManager->getRepository(EudoEntityMetadata::class);

            $occurrences = [];

            foreach ($webinar['occurrences'] as $occurrence)
                $occurrences[] = $occurrence['occurrence_id'];

            $eudoEntityMetadataRepository->create($formationCourse, ['occurrence_ids'=>$occurrences]);
        }

        $zoomUserPassword = $zoomUser ? $zoomUser['password'] : '';

        $this->zoomConnector->addWebinarPanelists($webinar['id'], $panelists);
        $zoomPanelists = $this->zoomConnector->getWebinarPanelists($webinar['id']);

        foreach ($formationCourse->getInstructors() as $instructor) {

            if( !$email = $instructor->getEmail() )
                continue;

            if( !$zoomPanelist = ($zoomPanelists[$email]??false) ){

                $this->mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'],'Panelist not found using email : '.$email, '');
            }
            else{

                $body = $this->mailer->createBodyMail('e-learning/zoom.html.twig', ['formationCourse'=>$formationCourse, 'formation'=>$formation, 'room'=>$room, 'instructor'=>$instructor, 'room_password'=>$zoomUserPassword, 'panelist'=>$zoomPanelist]);
                $this->mailer->sendMessage($email, $_ENV['ZOOM_DEFAULT_CONTACT_NAME'].' vous invite à animer un webinaire Zoom', $body, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);
            }
        }

        return $formationCourse->getWebinarId();
    }

    /**
     * @param $formationCourse
     * @return EudoEntityMetadata|EudoEntityMetadata[]|null
     * @throws ExceptionInterface
     * @throws ORMException
     */
    public function generateTimesheet($formationCourse){

        /** @var EudoEntityMetadataRepository $eudoEntityMetadataRepository */
        $eudoEntityMetadataRepository = $this->entityManager->getRepository(EudoEntityMetadata::class );

        $formation = $formationCourse->getFormation();

        $now = new DateTime();
        $date = $now->setTime(0,0)->format('y-m-d');

        if( !$formationCourseMetadata = $eudoEntityMetadataRepository->findByEntity($formationCourse) )
            $formationCourseMetadata = $eudoEntityMetadataRepository->create($formationCourse, []);

        $timesheet_url = $formationCourseMetadata->getData('timesheet_url');
        $timesheet_date = $formationCourseMetadata->getData('timesheet_date');

        if( !$timesheet_url || !$timesheet_date || $timesheet_date != $date ){

            try {

                $url = $this->eudonetAction->generateFile($_ENV['EUDONET_SIGNAGE_SHEET_ID'], 'formation_course', $formationCourse->getId());
                $error = false;

            } catch (\Throwable $t) {

                $url = false;
                $error = $t->getMessage();
            }

            if( $url ){

                $formationCourseMetadata->setData('timesheet_url', $url);
                $formationCourseMetadata->setData('timesheet_date', $date);

                $formationFoadRepository = $this->entityManager->getRepository(FormationFoad::class);
                $formationFoad = $formationFoadRepository->findOneBy(['formation'=>$formation]);

                if( !$formationFoad || empty($formationFoad->getQuiz()) )
                    $this->mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Le quiz est vide', 'Le quiz est vide pour la formation : '.$formation->getTitle());
            }
            else{

                $formationCourseMetadata->setData('error', $error);
                $this->mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'La création de la feuille de présence a générée une erreur', $error);
            }
        }

        return $formationCourseMetadata;
    }


        /**
     * @param $webinarId
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws Exception
     */
    public function startWebinar($webinarId){

        /** @var EudoEntityMetadataRepository $eudoEntityMetadataRepository */
        $eudoEntityMetadataRepository = $this->entityManager->getRepository(EudoEntityMetadata::class );

        /** @var FormationCourseRepository $formationCourseRepository */
        $formationCourseRepository = $this->entityManager->getRepository(FormationCourse::class );

        /** @var FormationCourse $formationCourse */
        if( !$formationCourse = $formationCourseRepository->findOneBy(['webinarId'=>$webinarId]) )
            throw new NotFoundHttpException('Formation course not found');

        $now = new DateTime();
        $event = ['event'=>'start', 'date'=>$now->getTimestamp()];

        $formationCourseMetadata = $this->generateTimesheet($formationCourse);

        if( $events = $formationCourseMetadata->getData('events') )
            $events[] = $event;
        else
            $events = [$event];

        $formationCourseMetadata->setData('events', $events);
        $eudoEntityMetadataRepository->save($formationCourseMetadata);
    }

    /**
     * @param $webinarId
     * @throws Exception
     * @throws ExceptionInterface
     * @throws ORMException
     */
    public function endWebinar($webinarId){

        /** @var FormationCourseRepository $formationCourseRepository */
        $formationCourseRepository = $this->entityManager->getRepository(FormationCourse::class );

        /** @var EudoEntityMetadataRepository $eudoEntityMetadataRepository */
        $eudoEntityMetadataRepository = $this->entityManager->getRepository(EudoEntityMetadata::class );

        /** @var FormationCourse $formationCourse */
        if( !$formationCourse = $formationCourseRepository->findOneBy(['webinarId'=>$webinarId]) )
            throw new NotFoundHttpException('Formation course not found');

        $now = new DateTime();
        $event = ['event'=>'end','date'=>$now->getTimestamp()];

        if( !$formationCourseMetadata = $eudoEntityMetadataRepository->findByEntity($formationCourse) )
            throw new NotFoundHttpException('Formation course metadata not found');

        if( $events = $formationCourseMetadata->getData('events') )
            $events[] = $event;
        else
            $events = [$event];

        $formationCourseMetadata->setData('events', $events);
        $eudoEntityMetadataRepository->save($formationCourseMetadata);

        if( $formationCourseMetadata->getData('processed') )
            return;

        $formation = $formationCourse->getFormation();

        foreach ($formationCourse->getInstructors() as $instructor ){

            $now->setTime(0,0);
            $completed = $now >= $formationCourse->getEndAt();

            if( $email = $instructor->getEmail() ){

                $body = $this->mailer->createBodyMail('e-learning/zoom-ended.html.twig', ['completed'=>$completed, 'formationCourse'=>$formationCourse, 'formation'=>$formation, 'instructor'=>$instructor]);
                $this->mailer->sendMessage($email, 'Mettre fin au webinaire et signer la feuille de présence', $body, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);
            }
            else{

                $this->logger->error('Instructor '.$instructor->getFirstname().' '.$instructor->getLastname().' has no email address');
            }
        }
    }

    /**
     * @param array $webinarParticipants
     * @param FormationParticipant $participant
     */
    private function getWebinarParticipantReport($webinarParticipants, $participant){

        if($address = $participant->getAddress() ){

            if($email = $address->getEmail() ){

                if(isset($webinarParticipants[$email]) )
                    return $webinarParticipants[$email];
            }
        }

        if( $contact = $participant->getContact() ) {

            foreach ($webinarParticipants as $webinarParticipant){

                if( $webinarParticipant['name'] == $contact->__toString() )
                    return $webinarParticipant;
            }
        }

        return false;
    }

    /**
     * @param $webinarId
     * @return FormationCourse
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws Exception
     */
    public function completeWebinar($webinarId){

        /** @var FormationCourseRepository $formationCourseRepository */
        $formationCourseRepository = $this->entityManager->getRepository(FormationCourse::class );

        /** @var EudoEntityMetadataRepository $eudoEntityMetadataRepository */
        $eudoEntityMetadataRepository = $this->entityManager->getRepository(EudoEntityMetadata::class );

        /** @var FormationParticipantConnectionRepository $formationParticipantConnectionRepository */
        $formationParticipantConnectionRepository = $this->entityManager->getRepository(FormationParticipantConnection::class );

        /** @var FormationCourse $formationCourse */
        if( !$formationCourse = $formationCourseRepository->findOneBy(['webinarId'=>$webinarId]) )
            throw new NotFoundHttpException('Formation course not found');

        if( !$formationCourseMetadata = $eudoEntityMetadataRepository->findByEntity($formationCourse) )
            throw new Exception('Formation course metadata not found');

        $events = $formationCourseMetadata->getData('events');

        $now = new DateTime();
        $today = new DateTime();
        $today->setTime(0,0);

        $completed = $today >= $formationCourse->getEndAt();

        if( !$completed ){

            foreach ($events as $event){

                if( $event['date'] == $today->getTimestamp() && $event['event'] == 'day_end' )
                    return $formationCourse;
            }

            $event = ['event'=>'day_end', 'timestamp'=>$now->getTimestamp(), 'date'=>$today->getTimestamp()];
        }
        else{

            if( $formationCourseMetadata->getData('processed') )
                return $formationCourse;

            $event = ['event'=>'complete', 'timestamp'=>$now->getTimestamp()];
        }

        if( $events )
            $events[] = $event;
        else
            $events = [$event];

        $formation = $formationCourse->getFormation();
        $participants = $formationCourse->getParticipants();

        $room = $formationCourse->getInstructor1();

        $webinarParticipants = $this->zoomConnector->getWebinarParticipantsReport($webinarId);

        foreach ($webinarParticipants as $webinarParticipant){

            if( $webinarParticipant['duration'] == 0 ){

                $this->mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Statistiques Zoom invalides', 'Nous avons détecté des durées invalides de participation pour la formation #'.$formationCourse->getWebinarId());
                throw new Exception('Zoom stats are not yet available');
            }
        }

        if( empty($webinarParticipants) )
            throw new Exception('Zoom stats are not yet available');

        if( !$room || !$webinarInstructor = ($webinarParticipants[$room->getEmail()]??false) )
            throw new Exception('Unable get instructor data');

        $addresses = [];

        $summary = [
            'completed'=>[],
            'absents'=>[]
        ];

        //todo: $formationCourse->getHours()*60 is not ok for multiple days formation, compute time/days
        $formationDuration = min($webinarInstructor['duration'], $formationCourse->getHours()*60);

        foreach ($participants as $participant){

            if( !$participant->getRegistered() )
                continue;

            $contact = $participant->getContact();
            $address = $participant->getAddress();
            $absent  = false;

            if( $webinarParticipant = $this->getWebinarParticipantReport($webinarParticipants, $participant) ){

                if( !$webinarParticipantMetadata = $eudoEntityMetadataRepository->findByEntity($participant) )
                    $webinarParticipantMetadata = $eudoEntityMetadataRepository->create($participant);

                foreach ($webinarParticipant['raw_log'] as $log)
                    $formationParticipantConnectionRepository->create($participant, $log);

                if( $webinarParticipant['duration'] >= $formationDuration*0.8 ){

                    if( $completed )
                        $webinarParticipantMetadata->setData('completed', true);

                    $summary['completed'][] = $participant;

                    if( $address && $email = $address->getEmail()){

                        $company = $address->getCompany();

                        $addresses[] = $address;

                        $title = $completed?'Fin de formation réussie':'Journée de formation réussie';
                        $bodyMail = $this->mailer->createBodyMail('e-learning/webinar-success.html.twig', ['title'=>$title, 'completed'=>$completed, 'contact'=>$contact, 'company'=>$company, 'formationCourse'=>$formationCourse, 'formation'=>$formation, 'webinarParticipant'=>$webinarParticipant]);
                        $this->mailer->sendMessage($email, $title, $bodyMail);
                    }
                }
                else{

                    $absent = true;
                }

                $eudoEntityMetadataRepository->save($webinarParticipantMetadata);
            }
            else{

                $absent = true;
            }

            if( $absent ) {

                if( !$participant->getAbsent() ){

                    if( $participant->getPresent() )
                        $this->eudonetConnector->update('formation_participant', $participant->getId(), ['present'=>false]);

                    try {

                        $this->eudonetConnector->update('formation_participant', $participant->getId(), ['absent' => true]);

                    } catch (Exception $e) {

                        $this->mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Impossible de changer le status en absent du participant', $participant->getContact()->getFullname().' Webinar #'.$formationCourse->getWebinarId());
                    }
                }

                $summary['absents'][] = $participant;

                if( $address && $email = $address->getEmail()){

                    $company = $address->getCompany();

                    $title = $completed?'Fin de formation échouée':'Journée de formation échouée';
                    $bodyMail = $this->mailer->createBodyMail('e-learning/webinar-absent.html.twig', ['title'=>$title, 'completed'=>$completed, 'company'=>$company, 'contact'=>$contact, 'formationCourse'=>$formationCourse, 'formation'=>$formation, 'webinarParticipant'=>$webinarParticipant]);
                    $this->mailer->sendMessage($email, $title, $bodyMail);
                }
            }
        }

        $formationCourseMetadata = $this->generateTimesheet($formationCourse);

        if( !$formationCourseMetadata->getData('timesheet_url') )
            throw new Exception('Timesheet url is not defined');

        $documentPath = $this->downloadTemp($formationCourseMetadata->getData('timesheet_url'));

        foreach ($formationCourse->getInstructors() as $instructor){

            if( $instructor->getEmail() )
                $addresses[] = $instructor->getAddress();
        }

        $documentParams = [
            'name'=>'timesheet',
            'fields'=>['width'=>150, 'height'=>60, 'per_row'=>5, 'page'=>1, 'offset_x'=>10, 'offset_y'=>10, 'origin_x'=>20, 'origin_y'=>278]
        ];

        $signature = $this->initiateSignatureCollect($addresses, $formationCourse, 'SNPI', [$documentPath=>$documentParams]);
        $signature->setExpiredAt('+1 day midnight');

        if( $completed ){

            $formationCourseMetadata->setData('processed', true);
            $eudoEntityMetadataRepository->save($formationCourseMetadata);
        }

        $this->entityManager->persist($signature);
        $this->entityManager->flush();

        $formationCourseMetadata->setData('events', $events);
        $eudoEntityMetadataRepository->save($formationCourseMetadata);

        $title = $completed?'Fin de formation':'Fin de journée de formation';
        $bodyMail = $this->mailer->createBodyMail('e-learning/webinar-report.html.twig', ['title'=>$title, 'completed'=>$completed, 'summary'=>$summary, 'formationCourse'=>$formationCourse, 'formation'=>$formation, 'webinarParticipants'=>$webinarParticipants]);
        $this->mailer->sendMessage($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], $title, $bodyMail);

        return $formationCourse;
    }


    /**
     * @param Address[] $addresses
     * @param AbstractEntity $entity
     * @param $account
     * @param array $files
     * @return Signature
     * @throws Exception
     */
    public function initiateSignatureCollect(array $addresses, AbstractEntity $entity, $account, $files=[]){

        /** @var SignatoryRepository $signatoryRepository */
        $signatoryRepository = $this->entityManager->getRepository(Signatory::class );

        $entityClass = ClassUtils::getRealClass(get_class($entity));

        $signature = new Signature();

        $signature->setEntity($entityClass);
        $signature->setEntityId($entity->getId());
        $signature->setAccount($account);
        $signature->setCount(count($addresses));
        $signature->setStatus('open');

        $this->contraliaAction->initiate($signature);

        foreach($files as $file=>$params)
            $this->contraliaAction->upload($signature, $file, $params);

        $i = 1;

        foreach ($addresses as $address){

            if( !$signatoryRepository->findOneBy(['address'=>$address, 'signature'=>$signature]) ) {

                $signatory = new Signatory();

                $signatory->setAddress($address);
                $signature->addSignatory($signatory);

                $this->contraliaAction->addSignatory($signatory, $i);
            }

            $i++;
        }

        return $signature;
    }

    /**
     * @param UserInterface $user
     * @return int
     * @throws Exception
     */
    public function generateCertificates(UserInterface $user)
    {
        $count = 0;

        if ( $contact = $user->getContact() ) {

            if ( $contracts = $this->eudonetAction->getContracts($contact) ) {

                $addresses = $contact->getAddresses();

                /** @var AppendixRepository $appendixRepository */
                $appendixRepository = $this->entityManager->getRepository(Appendix::class );
                /** @var ContractRepository $appendixRepository */
                $contractRepository = $this->entityManager->getRepository(Contract::class);

                foreach ($contracts as $contract) {

                    if ( $contract['category'] == 'RCP CACI') {

                        foreach ($addresses as $address) {
                            if ( !$address->isHome() && $address->isActive() ) {

                                $company = $address->getCompany();

                                if ( !$appendixRepository->findBy(['contact'=>$contact, 'entityId'=>$contract['id'], 'entityType'=>'contract', 'company'=>$company]) ) {

                                    if( $_contract = $contractRepository->find($contract['id']) ){

                                        $this->caciService->generateCertificate($user, $_contract, $address);
                                        $count++;
                                    }
                                }
                            }
                        }
                    }
                    elseif ( $contract['category'] == 'PJ CACI') {

                        if ( !$appendixRepository->findBy(['contact'=>$contact, 'entityId'=>$contract['id'], 'entityType'=>'contract', 'company'=>null]) ) {

                            if( $_contract = $contractRepository->find($contract['id']) ){

                                $this->caciService->generateCertificate($user, $_contract);
                                $count++;
                            }
                        }
                    }
                }
            }
        }

        return $count;
    }

    /**
     * @param $url
     * @return false|mixed
     * @throws Exception
     */
    public function downloadTemp($url){

        if( !$url )
            return false;

        $filesystem = new Filesystem();

        $filedir = $this->kernel->getCacheDir() .'/tmp';

        if( !is_dir($filedir ) )
            $filesystem->mkdir($filedir);

        $content = file_get_contents($url);

        if( !$content )
            throw new Exception('File content is empty');

        $filename = $filedir.'/'.md5($url);

        $filesystem->dumpFile($filename, $content);

        return $filename;
    }


    


    public function alertParticipant($formationParticipant, Mailer $mailer)
    {
        if( $formationParticipant->getRegistered() ) {

            $address = $formationParticipant->getAddress();

           

            if( $address && $email = $address->getEmail() ){

                $company = $address->getCompany();

              

                $formationCourse = $formationParticipant->getFormationCourse();
                $formation = $formationCourse->getFormation();

                $contact = $formationParticipant->getContact();

               

                $formationCourse->getFormat() === $formation::FORMAT_WEBINAR;

                //return $address->getEmail() . ' '.$formationCourse->getFormat();


                if( $formationCourse->getFormat() == $formation::FORMAT_WEBINAR ){

                    if( !$formationParticipant->getRegistrantId() )
                        return;

                    $registrants = $zoomConnector->getWebinarRegistrants($formationCourse->getWebinarId());

                    if( !$registrant = ($registrants[$email]??false)){

                        $mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Email participant invalide', 'Nous avons détecté un changement d\'email pour un des participants à la formation #'.$formationCourse->getWebinarId().', '.$email.'. Cet email n\'est pas enregistré sur Zoom pourtant le participant est inscrit.' );
                       
						$message = "formation participant ".$formationParticipant->getId()." email has changed";
                        return $message;
                    }

                    $bodyMail = $mailer->createBodyMail('e-learning/webinar-reminder.html.twig', ['title'=>'Rappel de votre formation', 'registrant'=>$registrant, 'company'=>$company, 'contact'=>$contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'formationParticipant'=>$formationParticipant]);

                    $mailer->sendMessage($email, 'Rappel de votre formation', $bodyMail, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);

					$message = $contact->getLastname()." reminded";
					return $message;
                    //$this->output->writeln("<info>".$contact->getLastname()." reminded</info>");
                }
                elseif( $formationCourse->getFormat() == $formation::FORMAT_INSTRUCTOR_LED ){
                    if( $formationFoad = $formation->getFoad() )
                        $documents = $formationFoad->getDocuments();
                    else
                        $documents = [];

                    $bodyMail = $mailer->createBodyMail('formation/instructor-led-reminder.html.twig', ['title'=>'Rappel de votre formation', 'company'=>$company, 'contact'=>$contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'formationParticipant'=>$formationParticipant]);

                    $mailer->sendMessage($email, 'Rappel de votre formation', $bodyMail, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], $documents);
                    //$this->output->writeln("<info>".$contact->getLastname()." reminded</info>");
					$message = $contact->getLastname()." reminded";
					return $message;
                }else {
                    $message = "Impossible d'envoyer un message car l'utilisation n'est  pas dans la formation ".$formation::FORMAT_INSTRUCTOR_LED . ' ou  '. $formation::FORMAT_WEBINAR;
                    return $message;
                }

            } else {

				

               // $this->error("formation participant ".$formationParticipant." as no address or email", false);
			   		$message = "formation participant ".$formationParticipant." as no address or email";
					return $message;
            }
        }else {
				$message = "L'utilisateur n'est pas enregistré à cette formation";
				return $message;
		}
    }


    public function alertInstructors($formationCourse)
    {
        $formation = $formationCourse->getFormation();

        if( $formationCourse->getFormat() == $formation::FORMAT_WEBINAR ){

            $room = $formationCourse->getInstructor1();

            if( $room ){

                if( !$email = $room->getEmail() ){

                   
                    return 'Room '.$room->getId().' has no email address';
                }

                if( !$zoomUser = $this->zoomConnector->getUser($email) ){

                    //$this->error('Instructor '.$room->getId().' does not exist', false);
                    return 'Instructor '.$room->getId().' does not exist';
                }

                $zoomUserPassword = $zoomUser ? $zoomUser['password'] : '';
                $zoomPanelists = $this->zoomConnector->getWebinarPanelists($formationCourse->getWebinarId());

                foreach ($formationCourse->getInstructors() as $instructor) {

                    if( $email = $instructor->getEmail() ){

                        $zoomPanelist = ($zoomPanelists[$email]??false);

                        $body = $this->mailer->createBodyMail('e-learning/zoom.html.twig',['formationCourse'=>$formationCourse, 'formation'=>$formation, 'room'=>$room, 'instructor'=>$instructor, 'room_password'=>$zoomUserPassword, 'panelist'=>$zoomPanelist]);
                        $this->mailer->sendMessage($email, 'Rappel : '.$_ENV['ZOOM_DEFAULT_CONTACT_NAME'].' vous invite à animer un webinaire Zoom', $body, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);

                        //$this->output->writeln("<info>Instructor ".$instructor->getLastname()." reminded : ".$email."</info>");

                        return "Instructor ".$instructor->getLastname()." reminded : ".$email;
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

                    //$this->output->writeln("<info>Instructor ".$instructor->getLastname()." reminded : ".$email."</info>");

                    return "Instructor ".$instructor->getLastname()." reminded : ".$email;
                }
            }
        }
    }
}
