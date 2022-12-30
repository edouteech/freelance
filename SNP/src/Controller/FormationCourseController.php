<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\FormationCourse;
use App\Form\Type\FormationCourseCreateType;
use App\Form\Type\FormationCourseReadType;
use App\Form\Type\FormationCourseReportReadType;
use App\Form\Type\FormationCourseSearchType;
use App\Repository\AgreementRepository;
use App\Repository\AppendixRepository;
use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Repository\EudoEntityMetadataRepository;
use App\Repository\ExternalFormationRepository;
use App\Repository\FormationCourseRepository;
use App\Repository\FormationFoadRepository;
use App\Repository\FormationInterestRepository;
use App\Repository\FormationParticipantRepository;
use App\Repository\PollRepository;
use App\Repository\SignatoryRepository;
use App\Repository\SurveyRepository;
use App\Service\EudonetAction;
use App\Service\Mailer;
use App\Service\ServicesAction;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\ORMException;
use Eluceo\iCal\Component\Calendar;
use Eluceo\iCal\Component\Event;
use Eluceo\iCal\Property\Event\Geo;
use Eluceo\iCal\Property\Event\Organizer;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * Formation course Controller
 *
 * @SWG\Tag(name="Formations Course")

 * @Security(name="Authorization")
*/
class FormationCourseController extends AbstractController
{
	/**
	 * Get all subscribed formations course
	 *
	 * @Route("/formation/course/subscribed", methods={"GET"})
	 *
	 * @IsGranted("ROLE_CLIENT")
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of formations per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 *
	 * @SWG\Response(response=200, description="Returns a list of formation courses")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param FormationParticipantRepository $formationParticipantRepository
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param CompanyRepository $companyRepository
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 */
	public function getSubscribed(Request $request, FormationParticipantRepository $formationParticipantRepository, FormationCourseRepository $formationCourseRepository, CompanyRepository $companyRepository)
	{
		$user = $this->getUser();

		list($limit, $offset) = $this->getPagination($request);

		$contact = $user->getContact();

		if( $user->isLegalRepresentative() )
			$contacts = $companyRepository->getContacts($user->getCompany());
		else
			$contacts = [$contact];

		$formationCourses = $formationCourseRepository->findByParticipants($contacts, $limit, $offset);

		$subscribed = [];

		foreach ($formationCourses as $formationCourse){

			$formationParticipants = $formationParticipantRepository->findAllByContacts($contacts, ['formationCourse'=>$formationCourse, 'registered'=>true]);

			$data = $formationCourseRepository->hydrate($formationCourse);
			$data['participants'] = $formationParticipantRepository->hydrateAll($formationParticipants, $formationParticipantRepository::$HYDRATE_CONTACTS);
			$data['completed'] = 0;

			foreach ($formationParticipants as $formationParticipant)
				$data['completed'] += $formationParticipant->getPresent();

			$subscribed[] = $data;
		}

		return $this->respondOK([
			'items'=>$subscribed,
			'count'=>count($formationCourses)
		]);
	}

	/**
	 * Get iCal
	 *
	 * @Route("/formation/course/subscribed/ical", methods={"GET"})
	 *
	 * @IsGranted("ROLE_CLIENT")
	 *
	 * @SWG\Response(response=200, description="Returns an ical file")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param FormationParticipantRepository $formationParticipantRepository
	 * @param CompanyRepository $companyRepository
	 * @param Request $request
	 * @return Response
	 */
	public function getICal(FormationParticipantRepository $formationParticipantRepository, CompanyRepository $companyRepository, Request $request)
	{
		$user = $this->getUser();

		if( $user->isLegalRepresentative() ){

			$company = $user->getCompany();
			$contacts = $companyRepository->getContacts($company);
		}
		else{

			$contacts = [$user->getContact()];
		}

		$formationParticipants = $formationParticipantRepository->findAllByContacts($contacts);

		$now = new DateTime();

		$iCalendar = new Calendar($request->getSchemeAndHttpHost());
		$iCalendar->setCalendarColor('#042b72');
		$iCalendar->setName('SNPI');

		$formationCourses = [];
		foreach ($formationParticipants as $formationParticipant){

			$formationCourse = $formationParticipant->getFormationCourse();

			if( !isset($formationCourses[$formationCourse->getId()]) )
				$formationCourses[$formationCourse->getId()] = ['entity'=>$formationCourse, 'participants'=>[]];

			$contact = $formationParticipant->getContact();
			$formationCourses[$formationCourse->getId()]['participants'][] = $contact->getFirstname() . ' ' . $contact->getLastname();
		}

		$count = 0;

		foreach ($formationCourses as $formationCourse){

			/** @var FormationCourse $formationCourseEntity */
			$formationCourseEntity = $formationCourse['entity'];

			if( $formationCourseEntity->getStartAt() > $now && $formationCourseEntity->getFormat() != 'e-learning' ){

				$formation = $formationCourseEntity->getFormation();
				$company = $formationCourseEntity->getCompany();

				$iEvent = new Event();

				$iEvent->setDtStart($formationCourseEntity->getStartAt())->setDtEnd($formationCourseEntity->getEndAt());
				$iEvent->setSummary( $formation->getTitle().' - '.$formationCourseEntity->getSchedule() );
				$iEvent->setNoTime(true);

				if( $company ){

					$iEvent->setLocation($company->getStreet().', '.$company->getZip().' '.$company->getCity().', '.$company->getCountry());

					if( $company->getLat() && $company->getLng() )
						$iEvent->setGeoLocation(new Geo($company->getLat(), $company->getLng()));

					$iEvent->setOrganizer(new Organizer($company->getName()));
				}

				if( count($formationCourse['participants']) > 1 )
					$participants = 'Participants: '.implode(', ', $formationCourse['participants']);
				else
					$participants = 'Participant: '. $formationCourse['participants'][0];

				$iEvent->setDescription($formation->getObjective().'\n'.$participants);
				$iEvent->setDuration($formation->getHours());

				$iCalendar->addComponent($iEvent);
				$count++;
			}
		}

		if( !$count )
			return $this->respondNotFound('No formation available to schedule.');

		$iCal = $iCalendar->render();

		return $this->respondContent($iCal, ['Content-Type'=>'text/calendar; charset=utf-8', 'Content-Disposition'=>'attachment; filename=formations-snpi.ics']);
	}


    /**
     * Get formations reports
     *
     * @Route("/formation/course/report", methods={"GET"})
     *
     * @IsGranted("ROLE_CLIENT")
     *
     * @SWG\Response(response=200, description="Returns a list of formations")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param ExternalFormationRepository $externalFormationRepository
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param ContactRepository $contactRepository
     * @param FormationCourseRepository $formationCourseRepository
     * @param AppendixRepository $appendixRepository
     * @param CompanyRepository $companyRepository
     * @return JsonResponse
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
	public function getReports(Request $request, ExternalFormationRepository $externalFormationRepository, FormationParticipantRepository $formationParticipantRepository, ContactRepository $contactRepository, FormationCourseRepository $formationCourseRepository, AppendixRepository $appendixRepository, CompanyRepository $companyRepository)
	{
		$user = $this->getUser();
        list($limit, $offset) = $this->getPagination($request);

        $form = $this->submitForm(FormationCourseReportReadType::class, $request);

        if( !$form->isValid() )
            return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

        $criteria = $form->getData();

        $company = $user->getCompany();

		if( $user->isLegalRepresentative() ){
            /** @var Contact[] $contacts */
			$contacts = $companyRepository->getContacts($company, true, $criteria, $limit, $offset);
            $count = $companyRepository->getContactsCount($company, true, $criteria);

			if( !$businessCard = $company->getBusinessCard() )
				return $this->respondNotFound('Business card not found');

			$startAt = $businessCard->getIssuedAt();
		}
		else{
			$contact = $user->getContact();
			$contacts = [$contact];
            $count = 1;

			$startAt = new DateTime('3 years ago');
			$startAt->modify('1 month ago');
		}

		$reports = [];
		foreach ($contacts as $contact){

			$senority = $contact->getSeniority($company);

			$_contact = $contactRepository->hydrate($contact);
			$_contact['isLegalRepresentative'] = $contact->isLegalRepresentative($company);

			$reports[$contact->getId()] = [
				'contact'=>$_contact,
				'valid'=>$user->isLegalRepresentative() ? $senority!==false : true,
				'senority'=>$senority,
				'quota'=>$contact->getFormationsQuota($senority),
				'completed'=>0,
				'count'=>0,
				'completedEthics'=>0,
				'completedDiscrimination'=>0,
				'formations'=>[],
				'list'=>[]
			];
		}

		// get formations
		$formationParticipants = $formationParticipantRepository->getLastFormations($contacts, $startAt);

		foreach ($formationParticipants as $formationParticipant){

			$contact = $formationParticipant->getContact();
			$contactId = $contact->getId();

			$formationCourse = $formationParticipant->getFormationCourse();
			$formation = $formationCourse->getFormation();

			if( in_array($formationCourse->getStatus(), ['completed', 'confirmed', 'suspended']) ){

				$reports[$contactId]['completed'] += $formation->getHours();
				$reports[$contactId]['completedEthics'] += $formation->getHoursEthics();
				$reports[$contactId]['completedDiscrimination'] += $formation->getHoursDiscrimination();

				$reports[$contactId]['formations'][] = ['formationCourse', $formationCourse, $formationParticipant];
			}
		}

		// get external formations
		$externalFormations = $externalFormationRepository->getLastFormations($contacts, $startAt);

		foreach ($externalFormations as $externalFormation){

			$contact = $externalFormation->getContact();
			$contactId = $contact->getId();

			$reports[$contactId]['completed'] += $externalFormation->getHours();
			$reports[$contactId]['completedEthics'] += $externalFormation->getHoursEthics();
			$reports[$contactId]['completedDiscrimination'] += $externalFormation->getHoursDiscrimination();

			$reports[$contactId]['formations'][] = ['externalFormation', $externalFormation];
		}

		// order merged formations by date
		foreach ($reports as $contactId=>&$report) {
			usort($report['formations'], function ($a, $b) {
				return $a[1]->getStartAt() < $b[1]->getStartAt();
			});
		}

		// hydrate list
		foreach ($reports as $contactId=>&$report){

			foreach ($report['formations'] as $formation){

				if( count($report['list']) < 3 ){

					if( $formation[0] === 'formationCourse' ){

						$formationReport = $formationCourseRepository->hydrate($formation[1]);
						$appendix = $formationParticipantRepository->findAppendix($formation[2]);
						$formationReport['appendix'] = $appendixRepository->hydrate($appendix);

						$report['list'][] = $formationReport;
					}
					else{

						$report['list'][] = $externalFormationRepository->hydrate($formation[1]);
					}

					unset($report['formations']);
				}

                $report['count']++;
			}
		}

		return $this->respondOK([
			'count' => $count,
			'items' => array_values($reports)
		]);
	}


	/**
	 * Get all formation courses
	 *
	 * @Route("/formation/course", methods={"GET"})
	 *
	 * @IsGranted("ROLE_CLIENT")
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of formations per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 * @SWG\Parameter(name="sort", in="query", type="string", description="Order sorting", default="startAt", enum={"distance", "startAt", "duration"})
	 * @SWG\Parameter(name="order", in="query", type="string", description="Order result", enum={"asc", "desc"}, default="asc")
	 * @SWG\Parameter(name="seat", in="query", type="integer", description="Min seat available", default=1, minimum=1)
	 * @SWG\Parameter(name="duration", in="query", type="number", description="Duration in hours")
	 * @SWG\Parameter(name="format[]", in="query", type="string", description="Format", enum={"e-learning", "instructor-led", "in-house"})
	 * @SWG\Parameter(name="location", in="query", type="string", description="Lat,lng")
	 * @SWG\Parameter(name="startAt", in="query", type="string", description="YYYY-mm-dd")
	 * @SWG\Parameter(name="endAt", in="query", type="string", description="YYYY-mm-dd")
     * @SWG\Parameter(name="updatedAt", in="query", type="string")
	 * @SWG\Parameter(name="ethics", in="query", type="number", description="Include ethics hours")
	 * @SWG\Parameter(name="discrimination", in="query", type="number", description="Include discrimination hours")
	 * @SWG\Parameter(name="search", in="query", type="string", description="Query")
	 * @SWG\Parameter(name="distance", in="query", type="integer", description="Distance radius")
	 * @SWG\Parameter(name="theme", in="query", type="integer", description="Formation theme")
	 * @SWG\Parameter(name="formation", in="query", type="integer", description="Formation id")
	 * @SWG\Parameter(name="advancedFilters", in="query", type="boolean", description="Return available filters")
	 *
	 * @SWG\Response(response=200, description="Returns a list of formation courses")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function list(FormationCourseRepository $formationCourseRepository, Request $request)
	{
		$user = $this->getUser();

		list($limit, $offset) = $this->getPagination($request);

		$form = $this->submitForm(FormationCourseReadType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		$formationCourses = $formationCourseRepository->query($user, $limit, $offset, $criteria);

		$formations = [];

		foreach ($formationCourses as $formationCourse){

		    if( $user->hasRole('ROLE_ADMIN') )
                $formation = $formationCourseRepository->hydrate($formationCourse, $formationCourseRepository::$HYDRATE_FULL);
		    else
		        $formation = $formationCourseRepository->hydrate($formationCourse);

		    $formation['distance'] = $this->getUserDistance($formationCourse->getCompany(), $user);
			$formations[] = $formation;
		}

		$data = [
			'items'=>$formations,
			'count'=>count($formationCourses),
			'limit'=>$limit,
			'offset'=>$offset
		];

		return $this->respondOK($data);
	}


    /**
     * Get formation course participants
     *
     * @Route("/formation/course/{id}/participants", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of participants per page", default=10, maximum=100, minimum=2)
     * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @SWG\Response(response=200, description="Returns participants list")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Formation not found")
     *
     * @param Request $request
     * @param FormationCourseRepository $formationCourseRepository
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param int $id
     * @return JsonResponse
     * @throws ExceptionInterface
     */
	public function listParticipants(Request $request, FormationCourseRepository $formationCourseRepository, FormationParticipantRepository $formationParticipantRepository, $id)
	{
		list($limit, $offset) = $this->getPagination($request);

		//return $this->respondNotFound($request->query->get('company'));
		//'company'=>  $request->query->get('company')

		if( !$formationCourse = $formationCourseRepository->find($id) )
			return $this->respondNotFound('Unable to find formation');

		$formationParticipants = $formationParticipantRepository->query($limit, $offset, ['formationCourse'=>$formationCourse, 'company'=> $request->query->get('company'),'member_id'=> $request->query->get('member_id'),'search'=> $request->query->get('search') ]);

		return $this->respondOK([
			'items'=>$formationParticipantRepository->hydrateAll($formationParticipants, $formationParticipantRepository::$HYDRATE_FULL),
			'count'=>count($formationParticipants),
			'limit'=>$limit,
			'offset'=>$offset
		]);
	}

    /**
     * Get one formation course
     *
     * @Route("/formation/course/{id}", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @IsGranted("ROLE_CLIENT")
     *
     * @SWG\Response(response=200, description="Returns one formation")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Formation not found")
     *
     * @param FormationCourseRepository $formationCourseRepository
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param CompanyRepository $companyRepository
     * @param FormationInterestRepository $formationInterestRepository
     * @param int $id
     * @return JsonResponse
     * @throws ExceptionInterface
     */
	public function find(FormationCourseRepository $formationCourseRepository, FormationParticipantRepository $formationParticipantRepository, CompanyRepository $companyRepository, FormationInterestRepository $formationInterestRepository, $id)
	{
		$user = $this->getUser();

		if( !$formationCourse = $formationCourseRepository->find($id) )
			return $this->respondNotFound('Unable to find formation');

		$data = $formationCourseRepository->hydrate($formationCourse, $formationCourseRepository::$HYDRATE_FULL);

		if( !$user->hasRole('ROLE_ADMIN') ){

			$data['distance'] = $this->getUserDistance($formationCourse->getCompany(), $user);
			$data['alert'] = false;

			$contact = $user->getContact();

			if( $user->isLegalRepresentative() ){

				$company = $user->getCompany();
				$contacts = $companyRepository->getContacts($company);
			}
			else{

				$contacts = [$contact];
			}

			if( $contact && $formationInterestRepository->findOneBy(['formationCourse'=>$formationCourse, 'contact'=>$contact]) )
				$data['alert'] = true;

			$formationParticipants = $formationParticipantRepository->findAllByContacts($contacts, ['formationCourse'=>$formationCourse, 'registered'=>true]);
			$data['participants'] = $formationParticipantRepository->hydrateAll($formationParticipants, $formationParticipantRepository::$HYDRATE_IDS);
		}

		return $this->respondOK($data);
	}


	/**
	 * Check formation course
	 *
	 * @Route("/formation/course/{id}/check", methods={"GET"}, requirements={"id"="\d+"})
	 *
	 * @IsGranted("ROLE_ADMIN")
	 * @Security(name="Authorization")
	 *
	 * @SWG\Response(response=200, description="Returns one formation")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Formation not found")
	 *
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param FormationFoadRepository $formationFoadRepository
	 * @param int $id
	 * @return Response
	 */
	public function check(FormationCourseRepository $formationCourseRepository, FormationFoadRepository $formationFoadRepository, $id)
	{
		if( !$formationCourse = $formationCourseRepository->find($id) )
			return $this->respondNotFound('Unable to find formation');

		$formation = $formationCourse->getFormation();
		$foad = $formation->getFoad();

		$content = $formationFoadRepository->hydrate($foad, $formationCourse->getFormat());

		return $this->respondOK([
			'content'=>$content,
			'title'=>$formation->getTitle()
		]);
	}


	/**
	 * Alert when place is available
	 *
	 * @Route("/formation/course/{id}/alert", methods={"POST"}, requirements={"id"="\d+"})
	 *
	 * @IsGranted("ROLE_CLIENT")
	 *
	 * @SWG\Response(response=200, description="Returns ok")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Formation not found")
	 *
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param FormationInterestRepository $formationInterestRepository
	 * @param Mailer $mailer
	 * @param int $id
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function alert(FormationCourseRepository $formationCourseRepository, FormationInterestRepository $formationInterestRepository, Mailer $mailer, $id)
	{
		$user = $this->getUser();

		if( !$formationCourse = $formationCourseRepository->findOneBy(['id'=>$id]) )
			return $this->respondNotFound('Unable to find formation');

		if( !$contact = $user->getContact() )
			return $this->respondNotFound('Contact not found');

		$formationInterestRepository->create($user, $formationCourse);

		if( $user->isCollaborator() ){

			/** @var Company $company */
			if( !$company = $user->getCompany() )
				return $this->respondNotFound('Company not found');

			$legalRepresentatives = $company->getLegalRepresentatives();
			$formation = $formationCourse->getFormation();

			foreach ($legalRepresentatives as $legalRepresentative){

				$body = $mailer->createBodyMail('formation/interest.html.twig', ['title'=>'Une formation intéresse votre collaborateur', 'formationCourse'=>$formationCourse, 'formation'=>$formation, 'contact'=>$contact, 'legalRepresentative'=>$legalRepresentative]);

				if( $email = $legalRepresentative->getEmail($company) )
					$mailer->sendMessage($email, 'Une formation intéresse votre collaborateur', $body);
			}
		}

		return $this->respondCreated();
    }

    /**
     * Cancel formation course for all participants
     *
     * @Route("/formation/course/{id}/cancel", methods={"POST"}, requirements={"id"="\d+"})
     *
     *
     * @SWG\Response(response=200, description="Returns ok")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Formation not found")
     *
     * @param AgreementRepository $agreementRepository
     * @param FormationCourseRepository $formationCourseRepository
     * @param EudonetAction $eudonetAction
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param CompanyRepository $companyRepository
     * @param Mailer $mailer
     * @param int $id
     * @return JsonResponse
     * @throws ExceptionInterface
     */
	public function cancel(AgreementRepository $agreementRepository, FormationCourseRepository $formationCourseRepository, EudonetAction $eudonetAction, FormationParticipantRepository $formationParticipantRepository, CompanyRepository $companyRepository, Mailer $mailer, $id)
	{
		$user = $this->getUser();

		if( !$formationCourse = $formationCourseRepository->findOneBy(['id'=>$id]) )
			return $this->respondNotFound('Unable to find formation');

        /** @var Contact $contact */
        $contact = $user->getContact();

        if( $user->isLegalRepresentative() ){

            /** @var Company $company */
            $company = $user->getCompany();
            $memberId = $company->getMemberId();

            $contacts = $companyRepository->getContactsId($company);
            $contact = $contact?:$company->getLegalRepresentative();

        } else {

            $memberId = $contact->getMemberId();
            $contacts = [$contact->getId()];
        }

        $agreements = $agreementRepository->findByUser($user, ['formationCourse'=>$formationCourse]);
        $formation = $formationCourse->getFormation();

        foreach ($agreements as $agreement){

            $participants = $formationParticipantRepository->findBy(['agreement'=>$agreement]);
            $participantsToCancel = [];

            foreach ($participants as $participant){

                $participantContact = $participant->getContact();
                $participantAddress = $participant->getAddress();

                if( $participantContact && in_array($participantContact->getId(), $contacts) ){

                    if( $participant->getRegistered() ){

                        $participant->setRegistered(false);
                        $eudonetAction->push($participant);

                        if( $participantAddress && $participantEmail = $participantAddress->getEmail() ){

                            $body = $mailer->createBodyMail('formation/unregistered.html.twig', ['title'=>'Annulation de formation', 'contact'=>$participantContact, 'formation'=>$formation, 'formationCourse'=>$formationCourse]);
                            $mailer->sendMessage($participantEmail, 'Annulation de formation', $body);
                        }
                    }

                    $participantsToCancel[] = $participant->getContact();
                }
            }

            $amount = $agreement->getAmount();

            if( !count($participantsToCancel) )
                continue;

            if( count($participantsToCancel) == count($participants) ){

                if( $invoice = $eudonetAction->generateRefund($agreement->getInvoiceId()) ){

                    $body = $mailer->createBodyMail('formation/refund.html.twig', ['title'=>'Avoir total généré', 'total'=>true, 'memberId'=>$memberId, 'contact'=>$contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'participants'=>$participantsToCancel, 'invoice'=>$invoice]);
                    $bodyCustomer = $mailer->createBodyMail('formation/refund-customer.html.twig', ['title'=>'Avoir total généré', 'total'=>true, 'memberId'=>$memberId, 'contact'=>$contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'participants'=>$participantsToCancel, 'invoice'=>$invoice]);
                }
            }
            else{

                $refund = ($amount/count($participants))*count($participantsToCancel);

                if( $invoice = $eudonetAction->generatePartialRefund($agreement->getInvoiceId(), $refund) ){

                    $body = $mailer->createBodyMail('formation/refund.html.twig', ['title'=>'Avoir partiel demandé', 'refund'=>$refund, 'total'=>false, 'memberId'=>$memberId, 'contact'=>$contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'participants'=>$participantsToCancel, 'invoice'=>$invoice]);
                    $bodyCustomer = $mailer->createBodyMail('formation/refund-customer.html.twig', ['title'=>'Avoir partiel demandé', 'refund'=>$refund, 'total'=>false, 'memberId'=>$memberId, 'contact'=>$contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'participants'=>$participantsToCancel, 'invoice'=>$invoice]);
                }
            }

            if( $invoice ){

                $mailer->sendMessage($_ENV['MAILER_TO'], 'Annulation de formation', $body, $user->getEmail());
                $mailer->sendMessage($user->getEmail(), 'Annulation de formation', $bodyCustomer, $_ENV['MAILER_TO']);
            }

        }

		return $this->respondOK();
	}

    /**
     * Get formation course order
     *
     * @Route("/formation/course/{id}/order", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @IsGranted("ROLE_MEMBER")
     *
     * @SWG\Response(response=200, description="Returns one formation")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Formation not found")
     *
     * @param AgreementRepository $agreementRepository
     * @param FormationCourseRepository $formationCourseRepository
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param AppendixRepository $appendixRepository
     * @param CompanyRepository $companyRepository
     * @param EudonetAction $eudonetAction
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
	public function getOrder(AgreementRepository $agreementRepository, FormationCourseRepository $formationCourseRepository, FormationParticipantRepository $formationParticipantRepository, AppendixRepository $appendixRepository, CompanyRepository $companyRepository, EudonetAction $eudonetAction, $id)
	{
		$user = $this->getUser();

		$formationCourse = $formationCourseRepository->findOneBy(['id'=>$id]);

		if( !$formationCourse )
			return $this->respondNotFound('Unable to find formation');

		$formation = $formationCourseRepository->hydrate($formationCourse, $formationCourseRepository::$HYDRATE_FULL);

		$agreements = $agreementRepository->findByUser($user, ['formationCourse'=>$formationCourse]);

		$formation['invoices'] = [];

        if( $agreements ){

			foreach($agreements as $agreement){

				if( $agreement->getInvoiceId() )
                    $formation['invoices'][] = $eudonetAction->getInvoice($agreement->getInvoiceId());
			}
		}

		if( $user->isLegalRepresentative() ){

			$company = $user->getCompany();
			$contacts = $companyRepository->getContacts($company);
		}
		else{

			$contacts = [$user->getContact()];
		}

		$formationParticipants = $formationParticipantRepository->findAllByContacts($contacts, ['formationCourse'=>$formationCourse]);
		$formation['participants'] = [];

		foreach ($formationParticipants as $formationParticipant){

			$participant = $formationParticipantRepository->hydrate($formationParticipant, $formationParticipantRepository::$HYDRATE_FULL);

			if( $formationParticipant->getPresent() ){

				$appendix =  $formationParticipantRepository->findAppendix($formationParticipant);
				$participant['appendix'] = $appendixRepository->hydrate($appendix);
			}

			$formation['participants'][] = $participant;
		}

		return $this->respondOK($formation);
	}

    /**
     * Get formation course documents
     *
     * @Route("/formation/course/{id}/documents", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @IsGranted("ROLE_MEMBER")
     *
     * @SWG\Response(response=200, description="Returns one formation")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Formation not found")
     *
     * @param AgreementRepository $agreementRepository
     * @param FormationCourseRepository $formationCourseRepository
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param AppendixRepository $appendixRepository
     * @param CompanyRepository $companyRepository
     * @param EudonetAction $eudonetAction
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
	public function getDocuments(AgreementRepository $agreementRepository, FormationCourseRepository $formationCourseRepository, FormationParticipantRepository $formationParticipantRepository, AppendixRepository $appendixRepository, CompanyRepository $companyRepository, EudonetAction $eudonetAction, $id)
	{
		$user = $this->getUser();

        $contact = $user->getContact();

        if( $user->isLegalRepresentative() )
            $contacts = $companyRepository->getContacts($user->getCompany());
        else
            $contacts = [$contact];

		$formationCourse = $formationCourseRepository->findOneBy(['id'=>$id]);

		if( !$formationCourse )
			return $this->respondNotFound('Unable to find formation');

        $formationParticipants = $formationParticipantRepository->findAllByContacts($contacts, ['formationCourse'=>$formationCourse, 'registered'=>true]);

        $data = [];

        $data['invoices'] = $agreementRepository->findAppendices($formationCourse, $user, 'facture');

        $appendices = $formationCourseRepository->findAppendices($formationParticipants);
        $data['certificates'] = $formationCourseRepository->hydrateAll($appendices, $formationCourseRepository::$HYDRATE_IDS);

        return $this->respondOK($data);
	}

	/**
	 * Handle formation course event from webhook
	 *
	 * @Route("/formation/course/event", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="Formation course updated")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Formation course not found")
	 *
	 * @param ServicesAction $services
	 * @param Request $request
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function processEvent(ServicesAction $services, Request $request)
	{
		$payload = $request->get('payload');
		$event = $request->get('event');

		if( $request->headers->get('authorization') != ($_ENV['ZOOM_WEBHOOK_EVENT_TOKEN']??''))
			return $this->respondError('Invalid authorization token');

		switch( $event ){

			case 'webinar.started':

				$services->startWebinar($payload['object']['id']);
				break;

			case 'webinar.ended':

				$services->endWebinar($payload['object']['id']);
				break;

			default :
				return $this->respondError('Invalid formation course event');
		}

		return $this->respondOK('Formation course event processed');
	}


	/**
	 * Handle formation course event manually
	 *
	 * @Route("/formation/course/event", methods={"GET"}, name="formation_course_event")
	 *
	 * @SWG\Response(response=200, description="Formation course updated")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Formation course not found")
	 *
	 * @param ServicesAction $services
	 * @param Request $request
	 * @return Response
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function simulateEvent(ServicesAction $services, Request $request)
	{
		$id = $request->get('id');

		$event = $request->get('event');

		switch( $event ){

			case 'webinar.completed':

				$formationCourse = $services->completeWebinar($id);
				$formation = $formationCourse->getFormation();

				$today = new DateTime();
				$today->setTime(0,0);

				$completed = $today >= $formationCourse->getEndAt();

				$title = $completed?'Formation terminée':'Journée de formation terminée';
				return $this->respondHtml('e-learning/webinar-completed.html.twig', ['title'=>$title, 'completed'=>$completed, 'formationCourse'=>$formationCourse, 'formation'=>$formation]);

			case 'webinar.started':
				$services->startWebinar($id);
				break;

			case 'webinar.ended':
				$services->endWebinar($id);
				break;

			default :
				return $this->respondHtmlError('Invalid formation course event');
		}

		return $this->respondHtmlOk('Formation course event processed');
	}

	/**
	 * Search formation course
	 *
	 * @Route("/formation/course/search", methods={"GET"})
	 *
	 * @SWG\Parameter(name="type", in="query", type="string")
	 * @SWG\Parameter(name="id", in="query", type="string")
	 *
	 * @SWG\Response(response=200, description="Return formation status")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Participant not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param EudoEntityMetadataRepository $entityMetadataRepository
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function search(Request $request, FormationCourseRepository $formationCourseRepository, EudoEntityMetadataRepository $entityMetadataRepository)
	{
		$form = $this->submitForm(FormationCourseSearchType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		if( !$formationCourse = $formationCourseRepository->findOneBy([$criteria['type']=>$criteria['id']]) )
			return $this->respondNotFound('Formation course not found');

		$formation = $formationCourse->getFormation();

		$today = new DateTime();
		$today->setTime(0,0);

		$completed = $today >= $formationCourse->getEndAt();
		$processed = false;

		if( $formationCourseMetadata = $entityMetadataRepository->findByEntity($formationCourse) ){

			if( !$completed ){

				$events = $formationCourseMetadata->getData('events');

				foreach ($events as $event){

					if( $event['date'] == $today->getTimestamp() && $event['event'] == 'day_end' ){

						$processed = true;
						break;
					}
				}
			}
			else{

				$processed = $formationCourseMetadata->getData('processed');
			}
		}

		return $this->respondOK([
			'id'=>$formationCourse->getId(),
			'multiDays'=>$formationCourse->getDays() > 1,
			'title'=>$formation->getTitle(),
			'startAt'=>$formationCourseRepository->formatDate($formationCourse->getStartAt(true)),
			'endAt'=>$formationCourseRepository->formatDate($formationCourse->getDayEndAt(true)),
			'processed'=>$processed,
			'completed'=>$completed
		]);
	}

	/**
	 * Get formation course status
	 *
	 * @Route("/formation/course/{id}/status", methods={"GET"})
	 *
	 * @SWG\Parameter(name="email", in="query", type="string")
	 *
	 * @SWG\Response(response=200, description="Return formation status")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Participant not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param EudonetAction $eudonetAction
	 * @param ContactRepository $contactRepository
	 * @param SignatoryRepository $signatoryRepository
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param FormationParticipantRepository $formationParticipantRepository
	 * @param $id
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function getStatus(Request $request, EudonetAction $eudonetAction, ContactRepository $contactRepository, SignatoryRepository $signatoryRepository, FormationCourseRepository $formationCourseRepository, FormationParticipantRepository $formationParticipantRepository, $id)
	{
		if( !$formationCourse = $formationCourseRepository->find($id) )
			return $this->respondNotFound('Formation course not found');

		if( !$formationCourseRepository->isActive($formationCourse) )
			return $this->respondError('Formation course is not active');

		$email = strtolower($request->get('email'));

		foreach ($formationCourse->getInstructors() as $instructor){

            if( $email && strtolower($instructor->getEmail()) == $email ){

                $data = $contactRepository->hydrate($instructor, $contactRepository::$HYDRATE_INSTRUCTOR);

                $data['completed'] = $data['poll'] = $data['survey'] = true;

                $signatory = $signatoryRepository->findOneByEntity($formationCourse, $instructor->getAddress());
                $data['present'] = $signatory && $signatory->getStatus() == 'signed';

                $data['type'] = 'instructor';
                $data['phone'] = false;
                $data['id'] = 'instructor-'.$formationCourse->getId().'-'.$instructor->getId();

                return $this->respondOK($data);
            }
		}

		if( !$formationParticipant = $formationParticipantRepository->findOneByEmail($formationCourse, $email ))
			return $this->respondNotFound('Participant not found');

		$eudonetAction->pull($formationParticipant);

		$data = $formationParticipantRepository->hydrate($formationParticipant, $formationParticipantRepository::$HYDRATE_FULL);

		$data['type'] = 'formationParticipant';
		$data['phone'] = false;

		return $this->respondOK($data);
	}

    /**
     * Create Formation Course
     *
     * @Route("/formation/course/{id}", defaults={"id"=0}, methods={"POST"})
     *
     * @IsGranted("ROLE_ADMIN")
     * @Security(name="Authorization")
     *
     * @SWG\Response(response=200, description="Returns a formation course")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @SWG\Parameter( name="contact", in="body", required=true, description="Contact information", @SWG\Schema( type="object",
     *     @SWG\Property(property="schedule", type="string"),
     *     @SWG\Property(property="startAt", type="string"),
     *     @SWG\Property(property="endAt", type="string"),
     *     @SWG\Property(property="seatingCapacity", type="integer"),
     *     @SWG\Property(property="instructor1", type="object"),
     *     @SWG\Property(property="instructor2", type="object"),
     *     @SWG\Property(property="instructor3", type="object"),
     *     @SWG\Property(property="taxRate", type="number"),
     *     @SWG\Property(property="format", type="string", enum={"instructor-led","in-house","e-learning","webinar"}),
     *     @SWG\Property(property="status", type="string", enum={"completed","canceled","potential","confirmed","delayed"})
     * ))
     *
     * @param Request $request
     * @param FormationCourseRepository $formationCourseRepository
     * @param EudonetAction $eudonet
     * @param $id
     * @return JsonResponse
     *
     * @throws ExceptionInterface
     */
	public function create(Request $request, FormationCourseRepository $formationCourseRepository, EudonetAction $eudonet, $id)
	{
	    if( !$id ){

            $formationCourse = new FormationCourse();
        }
	    else{

            if( !$formationCourse = $formationCourseRepository->find($id) )
                return $this->respondNotFound('Formation course not found');
        }

		$formationCourseForm = $this->submitForm(FormationCourseCreateType::class, $request, $formationCourse);

		if( !$formationCourseForm->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($formationCourseForm));

		if( $formationCourse->getInstructor2() && !$formationCourseRepository->isSlotAvailable($formationCourse, $formationCourse->getInstructor2()) )
			return $this->respondBadRequest('Slot is not available for instructor 2');

		if( $formationCourse->getInstructor3() && !$formationCourseRepository->isSlotAvailable($formationCourse, $formationCourse->getInstructor3()) )
			return $this->respondBadRequest('Slot is not available instructor 3');

		$eudonet->push($formationCourse);

        return $this->respondOk(
            $formationCourseRepository->hydrate($formationCourse, $formationCourseRepository::$HYDRATE_FULL)
        );
	}


    /**
     * Get formations statistics
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @Route("/formation/course/statistics", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Returns formation statistics")
     * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Parameter(name="startAt", in="query", type="string", description="date start", default="id", enum={"id"})
	 * @SWG\Parameter(name="endAt", in="query", type="string", description="date end", default="id", enum={"id"})
     *
     * @param SurveyRepository $surveyRepository
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param PollRepository $pollRepository
     * @param FormationCourseRepository $formationCourseRepository
     * @return JsonResponse
     */
	public function getStatistics(SurveyRepository $surveyRepository, FormationParticipantRepository $formationParticipantRepository, PollRepository $pollRepository, FormationCourseRepository $formationCourseRepository, Request $request)
	{
		if(!empty($request->query->get('startAt')) && !empty($request->query->get('endAt')))
		{
			$startAt = $request->query->get('startAt');
			$endAt = $request->query->get('endAt');
		}else {
			$endAt = (new \DateTime());
			$startAt = (new \DateTime())->modify('-1 year')->setTime(0,0,0);
		}
			
		return $this->respondOK([
			'poll'=>$pollRepository->getResponsesbyMonths($startAt, $endAt),
			'survey'=>$surveyRepository->getResponsesbyMonths($startAt, $endAt),
			'completed'=>$formationCourseRepository->getByMonths('completed', $startAt, $endAt),
			'canceled'=>$formationCourseRepository->getByMonths('canceled', $startAt, $endAt),
			'participants'=>[
				'present'=>$formationParticipantRepository->getbyMonths('present', $startAt, $endAt),
				'absent'=>$formationParticipantRepository->getbyMonths('absent', $startAt, $endAt)
			]
		]);
	}
}
