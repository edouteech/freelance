<?php

namespace App\Controller\FormationCourse;

use App\Controller\AbstractController;
use App\Form\Type\FormationCourseExportType;
use App\Repository\DownloadRepository;
use App\Repository\FormationCourseRepository;
use App\Repository\PollRepository;
use App\Repository\SurveyCommentRepository;
use App\Traits\SpreadsheetTrait;
use Doctrine\ORM\ORMException;
use Nelmio\ApiDocBundle\Annotation\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use App\Repository\SurveyRepository;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * Formation course Controller
 *
 * @IsGranted("ROLE_ADMIN")
 *
 * @SWG\Tag(name="Formations Course")

 * @Security(name="Authorization")
*/
class ExportController extends AbstractController
{
	use SpreadsheetTrait;

	/**
	 * Export formation course statistics as xlsx
	 *
	 * @Route("/formation/course/{id}/export/statistics", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Return xlsx file")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Formation course not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param SurveyRepository $surveyRepository
	 * @param SurveyCommentRepository $surveyCommentRepository
	 * @param int $id
	 *
	 * @return Response
	 */
	public function exportStatistics(Request $request, DownloadRepository $downloadRepository, FormationCourseRepository $formationCourseRepository, SurveyRepository $surveyRepository, SurveyCommentRepository $surveyCommentRepository, int $id)
	{
		if( !$formationCourse = $formationCourseRepository->find($id) )
			return $this->respondNotFound('Formation course not found');

		if( !$surveys = $surveyRepository->getStatisticsByFormationCourse($formationCourse) )
			return $this->respondNotFound('Surveys not found for this formation course');

		$statistics = [];

		if( $formation = $formationCourse->getFormation() ) {

			$instructors = [];

			foreach ($formationCourse->getInstructors() as $instructor)
				$instructors[] = $instructor->getFirstname().' '.$instructor->getLastname();

			$statistics[strtoupper('Session de formation')] = [
				"values" => [
					"Theme de l'intervention" => $formation->getTheme(),
					"Format" => $formation->getFormat(),
					"Intervenant(s)" => implode(', ', $instructors)
				],
				"conditions" => [
					"displayType" => "horizontal",
					"tableWidth" => 2,
				]
			];
		}

		foreach ($surveys as $survey) {

			$statistics[strtoupper($survey['group'])]['values'][$survey['question']][] = [
				'answer' => $survey['answer'],
				'countAnswer' => $survey['countAnswers']
			];
		}

		foreach ($statistics as $key => $statistic) {

			if($key == strtoupper('Session de formation'))
				continue;

			$maxLength = 0;

			foreach ($statistic['values'] as $value) {

				$length = count($value);

				if($maxLength < $length)
					$maxLength = $length;

			}

			$statistics[$key]['conditions'] = [
				"displayType" => "vertical",
				"tableWidth" => $maxLength,
			];
		}

		if( $comments = $surveyCommentRepository->findByFormationCourse($formationCourse) ) {

			$key = strtoupper('Remarques generales importantes');

			$statistics[$key]['values'] = array_map(
				function ($comment) {
					return $comment->getValue();
				},
				$comments
			);

			$statistics[$key]['conditions'] = [
				'displayType' => 'vertical',
				'tableWidth' => 1
			];

		}

		$file = $this->generateStatisticsSpreadsheet($statistics, 'formation_course');

		$download = $downloadRepository->create($request, $file['filepath'], true,  $file['filename']);

		return $this->respondOK($downloadRepository->hydrate($download));
	}

	/**
	 * CSV export of participants responses
	 *
	 * @Route("/formation/course/{id}/export/poll", methods={"GET"})
	 *
	 * @SWG\Parameter(name="contact", in="query", type="integer")
	 *
	 * @SWG\Response(response=200, description="Return csv flux")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Formation course not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param DownloadRepository $downloadRepository
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param PollRepository $pollRepository
	 * @param int $id
	 *
	 * @return Response
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function exportPoll(Request $request, DownloadRepository $downloadRepository, FormationCourseRepository $formationCourseRepository, PollRepository $pollRepository, int $id)
	{
		$form = $this->submitForm(FormationCourseExportType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest("Invalid arguments.", $this->getErrors($form));

		$criteria = $form->getData();

		if( !$formationCourse = $formationCourseRepository->find($id) )
			return $this->respondError('Formation course not found');

		$rows = $pollRepository->export($formationCourse, $criteria);

		$filename = $formationCourse->getFormation().'_'.$formationCourse->getStartAt()->format('Y_m_d').'.csv';

		$download = $downloadRepository->createFromCSVData($request, $filename, $rows);

		return $this->respondOK($downloadRepository->hydrate($download));
	}

	/**
	 * CSV export of participants responses
	 *
	 * @Route("/formation/course/{id}/export/survey", methods={"GET"})
	 *
	 * @SWG\Parameter(name="contact", in="query", type="integer")
	 *
	 * @SWG\Response(response=200, description="Return csv flux")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Formation course not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param DownloadRepository $downloadRepository
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param SurveyRepository $surveyRepository
	 * @param int $id
	 *
	 * @return Response
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function exportSurvey(Request $request, DownloadRepository $downloadRepository, FormationCourseRepository $formationCourseRepository, SurveyRepository $surveyRepository, int $id)
	{
		$form = $this->submitForm(FormationCourseExportType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest("Invalid arguments.", $this->getErrors($form));

		$criteria = $form->getData();

		if( !$formationCourse = $formationCourseRepository->find($id) )
			return $this->respondNotFound('Formation course not found');

		$rows = $surveyRepository->export($formationCourse, $criteria);

		$filename = $formationCourse->getFormation().'_'.$formationCourse->getStartAt()->format('Y_m_d').'.csv';

		$download = $downloadRepository->createFromCSVData($request, $filename, $rows);

		return $this->respondOK($downloadRepository->hydrate($download));
	}
}