<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\FormationCourse;
use App\Entity\FormationFoad;
use App\Entity\FormationParticipant;
use App\Form\Type\SurveyType;
use App\Repository\DownloadRepository;
use App\Repository\EudoEntityMetadataRepository;
use App\Repository\FormationCourseRepository;
use App\Repository\FormationParticipantConnectionRepository;
use App\Repository\FormationParticipantRepository;
use App\Repository\PollRepository;
use App\Repository\SignatoryRepository;
use App\Repository\SurveyCommentRepository;
use App\Repository\SurveyQuestionGroupRepository;
use App\Repository\SurveyRepository;
use App\Response\TransparentPixelResponse;
use App\Service\ContraliaAction;
use App\Service\EudonetAction;
use App\Service\Mailer;
use App\Service\ServicesAction;
use App\Traits\SpreadsheetTrait;
use Combodo\DoctrineEncryptBundle\Services\Encryptor;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use App\Repository\ContactRepository;
use App\Service\ZoomConnector;



/**
 * Formations Participant Controller
 *
 * @SWG\Tag(name="Formations Participant")
 *
 */
class FormationParticipantController extends AbstractController
{
	use SpreadsheetTrait;

	/**
	 * @param $id
	 * @return FormationParticipant
	 * @throws Exception
	 */
	private function getParticipant($id){

		/** @var FormationCourseRepository $formationCourseRepository */
		$formationCourseRepository = $this->entityManager->getRepository(FormationCourse::class);

		/** @var FormationParticipantRepository $formationParticipantRepository */
		$formationParticipantRepository = $this->entityManager->getRepository(FormationParticipant::class);

		if( is_string($id) && strpos($id, 'instructor-') !== false ){

			$id = explode('-', $id);

			if( count($id) != 3 )
				throw new NotFoundHttpException('Formation instructor not found');

			if( !$formationCourse = $formationCourseRepository->find($id[1]) )
				throw new NotFoundHttpException('Formation course not found');

			if( !$instructor = $formationCourse->getInstructorById($id[2]) )
				throw new NotFoundHttpException('Formation instructor not found');

			$formationParticipant = new FormationParticipant();
			$formationParticipant->setAddress($instructor->getAddress());
			$formationParticipant->setContact($instructor);
			$formationParticipant->setPresent(true);
			$formationParticipant->setFormationCourse($formationCourse);
		}
		else{

			if( !$formationParticipant = $formationParticipantRepository->find($id) )
				throw new NotFoundHttpException('Formation participant not found');

			$formationCourse = $formationParticipant->getFormationCourse();
		}

		if( !$formationCourse )
			throw new NotFoundHttpException('Formation course not found');

		if( !$formationCourseRepository->isActive($formationCourse) )
			throw new NotFoundHttpException('Formation course is not active');

		return $formationParticipant;
	}

	/**
	 * Confirm participation
	 *
	 * @Route("/formation/participant/{registrantId}/confirm", methods={"GET"}, name="confirm_formation_participation")
	 *
	 * @SWG\Response(response=200, description="Transparent pixel")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param EudonetAction $eudonetAction
	 * @param FormationParticipantRepository $formationParticipantRepository
	 * @param $registrantId
	 * @return TransparentPixelResponse
	 * @throws ExceptionInterface
	 */
	public function confirm(EudonetAction $eudonetAction, FormationParticipantRepository $formationParticipantRepository, $registrantId)
	{
		if( $formationParticipant = $formationParticipantRepository->findOneBy(['registrantId'=>$registrantId]) ){

			if( !$formationParticipant->getConfirmed() ){

				$formationParticipant->setConfirmed(true);

				$formationParticipant->setPresent(null);
				$formationParticipant->setAbsent(null);

				$eudonetAction->push($formationParticipant);
			}
		}

		return $this->respondTransparentPixel();
	}

	/**
	 * Get poll
	 *
	 * @Route("/formation/participant/{id}/poll", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Return poll")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Poll not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param $id
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function findPoll($id)
	{
		$formationParticipant = $this->getParticipant($id);
		$formationCourse = $formationParticipant->getFormationCourse();
		$formation = $formationCourse->getFormation();

		$formationFoadRepository = $this->entityManager->getRepository(FormationFoad::class);

        if( !$formationFoad = $formationFoadRepository->findOneBy(['formation'=>$formation]) )
            return $this->respondError('Formation foad is empty');

		if( !$poll = $formationFoad->getQuiz() )
			return $this->respondError('Poll is empty');

		return $this->respondOK($poll);
	}

	/**
	 * Send poll
	 *
	 * @Route("/formation/participant/{id}/poll", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="Return current user")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="User not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param EudonetAction $eudonetAction
	 * @param PollRepository $pollRepository
	 * @param $id
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function sendPoll(Request $request, EudonetAction $eudonetAction, PollRepository $pollRepository, $id)
	{
		$formationParticipant = $this->getParticipant($id);

        $quizId = $request->get('quizId');

        if( !$quizId )
            $eudonetAction->pull($formationParticipant);

        $formationCourse = $formationParticipant->getFormationCourse();
        $formation = $formationCourse->getFormation();

        $formationFoadRepository = $this->entityManager->getRepository(FormationFoad::class);

        if( !$formationFoad = $formationFoadRepository->findOneBy(['formation'=>$formation]) )
            return $this->respondError('Formation foad is empty');

        $questions = $formationFoad->getQuiz($quizId);

        foreach ($request->get('answers') as $question=>$answers){

			foreach ((array)$answers as $answer)
				$pollRepository->create($quizId, $answer, $question, $formationParticipant, $questions);
		}

        if( !$quizId && !$formationParticipant->getPoll() ){

            $formationParticipant->setPoll(true);
            $eudonetAction->push($formationParticipant);
        }

        return $this->respondOK();
	}

	/**
	 * Get survey
	 *
	 * @Route("/formation/participant/{id}/survey", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Return survey")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Survey not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param SurveyQuestionGroupRepository $surveyQuestionGroupRepository
	 * @param $id
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function findSurvey(SurveyQuestionGroupRepository $surveyQuestionGroupRepository, $id)
	{
		$formationParticipant = $this->getParticipant($id);
		$formationCourse = $formationParticipant->getFormationCourse();

		$survey = [];

		$surveyQuestionGroups = $surveyQuestionGroupRepository->findAll();

		foreach ($surveyQuestionGroups as $surveyQuestionGroup){

			$group = [
				'id'=>$surveyQuestionGroup->getId(),
				'title'=>$surveyQuestionGroup->getTitle(),
				'questions'=>[]
			];

			$surveyQuestions = $surveyQuestionGroup->getQuestions();

			foreach ($surveyQuestions as $surveyQuestion){

				if( !$surveyQuestion->getFormat() || $surveyQuestion->getFormat() == $formationCourse->getFormat() ){

					$question = [
						'id'=>$surveyQuestion->getId(),
						'title'=>$surveyQuestion->getTitle(),
						'answers'=>[]
					];

					$surveyAnswers = $surveyQuestion->getAnswers();

					foreach ($surveyAnswers as $surveyAnswer){

						$question['answers'][] = [
							'id'=>$surveyAnswer->getId(),
							'title'=>$surveyAnswer->getTitle()
						];
					}

					$group['questions'][] = $question;
				}
			}

			$survey[] = $group;
		}

		return $this->respondOK($survey);
	}

	/**
	 * Send survey
	 *
	 * @Route("/formation/participant/{id}/survey", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="Return current user")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="User not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param EudonetAction $eudonetAction
	 * @param SurveyRepository $surveyRepository
	 * @param SurveyCommentRepository $surveyCommentRepository
	 * @param $id
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function sendSurvey(Request $request, EudonetAction $eudonetAction, SurveyRepository $surveyRepository, SurveyCommentRepository $surveyCommentRepository, $id)
	{
		$formationParticipant = $this->getParticipant($id);

		$form = $this->submitForm(SurveyType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$form = $form->getData();

		$eudonetAction->pull($formationParticipant);

		foreach ($form['answers'] as $data)
			$surveyRepository->create($data['answer'], $data['question'], $formationParticipant);

		if( !empty($form['comment']) )
			$surveyCommentRepository->create($form['comment'], $formationParticipant);

        if( $formationParticipant->getSurvey() )
            return $this->respondOK();

		$formationParticipant->setSurvey(true);

		$eudonetAction->push($formationParticipant);

		return $this->respondOK();
	}

    /**
     * Send survey
     *
     * @Route("/formation/participant/{id}/terminate", methods={"POST"})
     *
     * @SWG\Response(response=200, description="Return current user")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param EudonetAction $eudonetAction
     * @param EudoEntityMetadataRepository $entityMetadataRepository
     * @param $id
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     */
	public function terminate(EudonetAction $eudonetAction, EudoEntityMetadataRepository $entityMetadataRepository, $id)
	{
		$formationParticipant = $this->getParticipant($id);
		$formationCourse = $formationParticipant->getFormationCourse();
		$formation = $formationCourse->getFormation();
		$foad = $formation->getFoad();
        $progress = $formationParticipant->getProgress();

        if( $formationCourse->getFormat() == Formation::FORMAT_E_LEARNING ){

            $write = $foad->getWrite();
            $chapters = count($write['chapters'])-1;
            $subchapters = count($write['chapters'][$chapters]['subchapters'])-1;

            if( !$progress || $progress->getChapter() < $chapters || $progress->getSubchapter() < $subchapters || !$formationParticipant->getSurvey() )
                return $this->respondError('Formation course is not completed');

            if( !$formationParticipant->getPresent() ){

                $formationParticipant->setPresent(true);
                $eudonetAction->push($formationParticipant);
            }
        }
        elseif( $formationCourse->getFormat() == Formation::FORMAT_WEBINAR ){

            if( !$formationParticipant->getPresent() )
                return $this->respondError('Formation course is not completed');
        }
        else{

            return $this->respondError('Invalid formation format');
        }

        if( !$formationParticipantMetadata = $entityMetadataRepository->findByEntity($formationParticipant) )
            $formationParticipantMetadata = $entityMetadataRepository->create($formationParticipant);

        if( !$formationParticipantMetadata->getData('attestation_url') ){

            $url = $eudonetAction->generateFile(126, 'formation_participant', $formationParticipant->getId());

            if( !$url )
                return $this->respondError('Attestation generation failed');

            $formationParticipantMetadata->setData('attestation_url', $url);
            $entityMetadataRepository->save($formationParticipantMetadata);
        }

		return $this->respondOK();
	}


	/**
	 * Get code
	 *
	 * @Route("/formation/participant/{id}/code", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Return current user")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="User not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param ContraliaAction $contraliaAction
	 * @param SignatoryRepository $signatoryRepository
	 * @param $id
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function getCode(ContraliaAction $contraliaAction, SignatoryRepository $signatoryRepository, $id)
	{
		$formationParticipant = $this->getParticipant($id);
		$formationCourse = $formationParticipant->getFormationCourse();

		if( !$signatory = $signatoryRepository->findOneByEntity($formationCourse, $formationParticipant->getAddress()) )
			return $this->respondError('Signatory not found');

		if( $signatory->getStatus() == 'signed' )
			return $this->respondError('Signature already done');

		$params = [
			'customMessage'=> "Bonjour, le code de sécurité pour la signature de votre feuille de présence est le : {OTP}.",
			'phone'=> false,
			'deliveryMode'=> 'EMAIL'
		];

		$status = $contraliaAction->getOtp($signatory, $params);

		return $this->respondOK($status);
	}

    /**
     * Check code
     *
     * @Route("/formation/participant/{id}/code", methods={"POST"})
     *
     * @SWG\Response(response=200, description="Return current user")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param ContraliaAction $contraliaAction
     * @param EudonetAction $eudonetAction
     * @param SignatoryRepository $signatoryRepository
     * @param EudoEntityMetadataRepository $entityMetadataRepository
     * @param $id
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws NonUniqueResultException
     * @throws Exception
     */
	public function checkCode(Request $request, ContraliaAction $contraliaAction, EudonetAction $eudonetAction, SignatoryRepository $signatoryRepository, EudoEntityMetadataRepository $entityMetadataRepository, $id)
	{
		if( !$otp = $request->get('otp') )
			return $this->respondError('Otp is empty');

		$formationParticipant = $this->getParticipant($id);
		$formationCourse = $formationParticipant->getFormationCourse();

		if( !$signatory = $signatoryRepository->findOneByEntity($formationCourse, $formationParticipant->getAddress()) )
			return $this->respondError('Signatory not found');

		$status = $signatory->getStatus() != 'signed' ? $contraliaAction->sign($signatory, $otp) : true;

		if( $status ){

			if( $signatory->getStatus() != 'signed' ){

				$signatory->setStatus('signed');
				$signatoryRepository->save($signatory);
			}

			$today = new DateTime();
			$today->setTime(0,0);

			$completed = $today >= $formationCourse->getEndAt();

			if( $completed && !$formationParticipant->getPresent() ){

				$formationParticipant->setPresent(true);
				$eudonetAction->push($formationParticipant);
			}
		}

		return $this->respondOK();
	}


    /**
     * Download appendix
     *
     * @Route("/formation/participant/{id}/download", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Return appendix")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=404, description="Appendix not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param Request $request
     * @param DownloadRepository $downloadRepository
     * @param EudoEntityMetadataRepository $entityMetadataRepository
     * @param $id
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws Exception
     */
	public function download(FormationParticipantRepository $formationParticipantRepository, Request $request, DownloadRepository $downloadRepository, EudoEntityMetadataRepository $entityMetadataRepository, $id)
	{
		if( !$formationParticipant = $formationParticipantRepository->find($id) )
			return $this->respondError('Formation participant not found');

		$formationParticipantMetadata = $entityMetadataRepository->findByEntity($formationParticipant);

		if( $formationParticipantMetadata && $attestationUrl = $formationParticipantMetadata->getData('attestation_url') ){

            $path = $this->storeRemoteFile($attestationUrl);
            $download = $downloadRepository->create($request, $path, true);

            return $this->respondOK($downloadRepository->hydrate($download));
        }

		return $this->respondNotFound('Unable to find document asset');
	}


	/**
	 * Cancel formation
	 *
	 * @Route("/formation/participant/{id}/cancel", methods={"GET"}, name="cancel_formation_participation")
	 *
	 * @SWG\Response(response=200, description="Return current user")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="User not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Mailer $mailer
	 * @param FormationParticipantRepository $formationParticipantRepository
	 * @param Encryptor $encryptor
	 * @param $id
	 * @return Response
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
    public function cancel(Mailer $mailer, FormationParticipantRepository $formationParticipantRepository, Encryptor $encryptor, $id)
    {
        $id = $encryptor->decrypt(base64_decode(urldecode($id)));

        if( !$formationParticipant = $formationParticipantRepository->find($id) )
            return $this->respondHtmlError('Formation participant not found');

        $formationCourse = $formationParticipant->getFormationCourse();
        $formation = $formationCourse->getFormation();
        $contact = $formationParticipant->getContact();
        $address = $formationParticipant->getAddress();
        $company = $address->getCompany();

        $bodyMail = $mailer->createBodyMail('e-learning/cancel.html.twig', ['title'=>'Un adhérent souhaite annuler sa formation', 'contact'=>$contact, 'formationCourse'=>$formationCourse, 'address'=>$address, 'formation'=>$formation, 'company'=>$company]);
        $mailer->sendMessage($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Annulation pour la formation '.$formationCourse->getWebinarId(), $bodyMail);

        return $this->respondHtmlOk('Un membre du SNPI va vous recontacter dans les plus brefs délais afin d\'annuler votre formation et procéder au remboursement.', 'Votre demande a été prise en compte');
    }


    /**
     * Validate formation course
     *
     * @Route("/formation/participant/{id}/validate", methods={"GET"}, name="validate_formation_participation")
     *
     * @SWG\Response(response=200, description="Return ok")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param ServicesAction $servicesAction
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param Encryptor $encryptor
     * @param $id
     * @return Response
     * @throws Exception
     */
    public function validate(ServicesAction $servicesAction, FormationParticipantRepository $formationParticipantRepository, Encryptor $encryptor, $id)
    {
        $id = $encryptor->decrypt(base64_decode(urldecode($id)));

        if( !$formationParticipant = $formationParticipantRepository->find($id) )
            return $this->respondHtmlError('Formation participant not found');

        $formationCourse = $formationParticipant->getFormationCourse();

        $address = $formationParticipant->getAddress();

        try {

            $servicesAction->registerForWebinar($formationCourse, $address->getContact(), $address->getCompany());
            return $this->respondHtmlOk('Votre participation a bien été enregistrée. Vous allez prochainement recevoir un e-mail de confirmation d\'inscription, comportant toutes les informations sur votre nouvelle formation.', 'Félicitations !');

        } catch (ExceptionInterface $e) {

            return $this->respondHtmlError($e->getMessage());
        }
    }


	/**
	 * Export formation participants connection log
	 *
	 * @Route("/formation/participant/connection", name="export_xlsx", methods={"GET"})
	 *
	 * @IsGranted("ROLE_ADMIN")
	 *
	 * @SWG\Response(response=200, description="Return xlsx")
	 *
	 * @param Request $request
	 * @param FormationParticipantConnectionRepository $formationParticipantConnectionRepository
	 * @param DownloadRepository $downloadRepository
	 * @return Response
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function exportXlsx(Request $request, FormationParticipantConnectionRepository $formationParticipantConnectionRepository, DownloadRepository $downloadRepository): Response
	{
		$formationParticipantConnections = $formationParticipantConnectionRepository->findAll();

		$file = $this->generateSpreadsheet(
			'FormationParticipantConnection',
			$formationParticipantConnections, [
				'lastName',
				'firstName',
				'memberId',
				'formationId',
				'formationName',
				'duration',
				'joinAt',
				'leaveAt'
			]
		);

		$download = $downloadRepository->create($request, $file['filepath'], true,  $file['filename']);

		return $this->respondOK($downloadRepository->hydrate($download));
	}


	/**
	 * Get all formation participants
	 *
	 * @Route("/formation/participant/list", methods={"GET"})
	 * @SWG\Parameter(name="search", in="query", type="string", description="Query")
	 * @SWG\Response(response=200, description="Returns a list of paricipants")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param FormationCourseRepository $formationParticipantRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function list(ContactRepository $contactRepository, Request $request)
	{

		list($limit, $offset) = $this->getPagination($request);

		$criteria= [];

		if(!empty($request->get('search'))) {

			$criteria['search'] = $request->get('search');

			$contacts = $contactRepository->findOneByEmailFirstNameLastname($limit, $offset, $criteria);

				$data = [
					'items'=>$contacts,
					'count'=>count($contacts),
					'limit'=>$limit,
					'offset'=>$offset
				];
		
			return $this->respondOK($data);
		}

	}


	/**
	 * Get all formation participants
	 *
	 * @Route("/formation/instructor/list", methods={"GET"})
	 * @SWG\Parameter(name="search", in="query", type="string", description="Query")
	 * @SWG\Response(response=200, description="Returns a list of paricipants")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param FormationCourseRepository $formationParticipantRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function listInstructor(ContactRepository $contactRepository, Request $request)
	{

		list($limit, $offset) = $this->getPagination($request);

		$criteria= [];

		if(!empty($request->get('search'))) {

			$criteria['search'] = $request->get('search');

			$contacts = $contactRepository->findInstructorByEmailFirstNameLastname($limit, $offset, $criteria);

				$data = [
					'items'=>$contacts,
					'count'=>count($contacts),
					'limit'=>$limit,
					'offset'=>$offset
				];
		
			return $this->respondOK($data);
		}

	}


	/**
	 * Alert Participant
	 *
	 * @Route("/formation/alert/{id}/participant", methods={"GET"})
	 * @SWG\Parameter(name="user_id", in="query", type="string", description="Query")
	 * @SWG\Response(response=200, description="Returns a list of paricipants")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param FormationCourseRepository $formationParticipantRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function alertuser(FormationParticipantRepository $formationParticipantRepository, $id, Mailer $mailer, ServicesAction $servicesAction)
	{

		if(!empty($id)) {

			if( !$formationParticipant = $formationParticipantRepository->find($id) ){
				return $this->respondHtmlError('Formation participant not found');
			}else {

				try{
					$address = $servicesAction->alertParticipant($formationParticipant,$mailer);
					return $this->respondOK($address);
				}
				catch (ExceptionInterface $e) {
					
					return $this->respondHtmlError($e->getMessage());
				}


			}
		}


		return $this->respondHtmlError('Identifiant is required');

	}


	/**
	 * Alert Instructor
	 *
	 * @Route("/formation/alert/{id}/instructor", methods={"GET"})
	 * @SWG\Parameter(name="user_id", in="query", type="string", description="Query")
	 * @SWG\Response(response=200, description="Returns a list of paricipants")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param FormationCourseRepository $formationParticipantRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function alertInstructor(FormationCourseRepository $formationCourseRepository, $id, Mailer $mailer, ServicesAction $servicesAction)
	{

		if(!empty($id)) {

			if( !$formationCourse = $formationCourseRepository->find($id) ){
				return $this->respondHtmlError('Formation participant not found');
			}else {

				try{
					$address = $servicesAction->alertInstructors($formationCourse);
					return $this->respondOK($address);
				}
				catch (ExceptionInterface $e) {
					
					return $this->respondHtmlError($e->getMessage());
				}


			}
		}


		return $this->respondHtmlError('Identifiant is required');

	}


	

	
}
