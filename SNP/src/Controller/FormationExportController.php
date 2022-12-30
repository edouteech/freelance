<?php

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\Poll;
use App\Entity\Survey;
use App\Form\Type\FormationCourseExportType;
use App\Repository\DownloadRepository;
use App\Repository\FormationCourseRepository;
use App\Repository\FormationRepository;
use App\Repository\SurveyCommentRepository;
use App\Traits\SpreadsheetTrait;
use Nelmio\ApiDocBundle\Annotation\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use App\Repository\SurveyRepository;


/**
 * Formation Controller
 *
 * @IsGranted("ROLE_ADMIN")
 *
 * @SWG\Tag(name="Formations")

 * @Security(name="Authorization")
*/
class FormationExportController extends AbstractController
{
    use SpreadsheetTrait;

    private function export(Request $request, FormationCourseRepository $formationCourseRepository, DownloadRepository $downloadRepository, $type)
    {
		$form = $this->submitForm(FormationCourseExportType::class, $request);

        if( !$form->isValid() )
            return $this->respondBadRequest("Invalid arguments.", $this->getErrors($form));

        $criteria = $form->getData();
        $criteria['format'] = Formation::FORMAT_WEBINAR;

        if( !$formationCourses = $formationCourseRepository->getConfirmed($criteria) )
            return $this->respondNotFound('Formation course not found');

        $rows = [];

        $surveyRepository = $this->entityManager->getRepository(Survey::class);
        $pollRepository = $this->entityManager->getRepository(Poll::class);

        foreach ($formationCourses as $formationCourse){

            if( $type == 'survey' )
                $_rows = $surveyRepository->export($formationCourse, $criteria);
            else
                $_rows = $pollRepository->export($formationCourse, $criteria);

            if( !empty($_rows) ){

                if( !empty($rows) )
                    array_shift($_rows);

                $rows = array_merge($rows, $_rows);
                array_push($rows, implode(';', ['','','','','','']));
            }
        }

        if( ($criteria['formation']??false) && !($criteria['contact']??false) )
            $filename = $criteria['formation']->getTitle();
        elseif( !($criteria['formation']??false) && ($criteria['contact']??false) )
            $filename = $criteria['contact'];
        else
            $filename = 'formations';

        if(($criteria['startAt']??false)||($criteria['endAt']??false))
            $filename .= '-'.(isset($criteria['startAt'])?$criteria['startAt']->format('Y-m-d'):'').'_'.(isset($criteria['endAt'])?$criteria['endAt']->format('Y-m-d'):'');

        $filename .= '-'.$type.'.csv';

	    $download = $downloadRepository->createFromCSVData($request, $filename, $rows);

	    return $this->respondOK($downloadRepository->hydrate($download));
    }

	/**
	 * CSV export of participants responses
	 *
	 * @Route("/formation/export/survey", methods={"GET"})
	 *
	 * @SWG\Parameter(name="startAt", in="query", type="string", description="YYYY-mm-dd")
	 * @SWG\Parameter(name="formation", in="query", type="integer")
	 * @SWG\Parameter(name="endAt", in="query", type="string", description="YYYY-mm-dd")
	 *
	 * @SWG\Response(response=200, description="Return csv flux")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Formation course not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param DownloadRepository $downloadRepository
	 * @return Response
	 */
	public function exportSurvey(Request $request, FormationCourseRepository $formationCourseRepository, DownloadRepository $downloadRepository)
	{
		return $this->export($request, $formationCourseRepository, $downloadRepository, 'survey');
	}


	/**
	 * CSV export of participants poll
	 *
	 * @Route("/formation/export/poll", methods={"GET"})
	 *
	 * @SWG\Parameter(name="startAt", in="query", type="string", description="YYYY-mm-dd")
	 * @SWG\Parameter(name="formation", in="query", type="integer")
	 * @SWG\Parameter(name="endAt", in="query", type="string", description="YYYY-mm-dd")
	 *
	 * @SWG\Response(response=200, description="Return csv flux")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Formation course not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param DownloadRepository $downloadRepository
	 * @return Response
	 */
	public function exportPoll(Request $request, FormationCourseRepository $formationCourseRepository, DownloadRepository $downloadRepository)
	{
        return $this->export($request, $formationCourseRepository, $downloadRepository, 'poll');
    }


	/**
	 * Export formation  statistics as xlsx
	 *
	 * @Route("/formation/export/statistics", methods={"GET"})
	 *
	 * @SWG\Parameter(name="formation", in="query", type="integer")
	 *
	 * @SWG\Response(response=200, description="Return xlsx file")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Formation course not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param FormationRepository $formationRepository
	 * @param DownloadRepository $downloadRepository
	 * @param SurveyRepository $surveyRepository
	 * @param SurveyCommentRepository $surveyCommentRepository
	 *
	 * @return Response
	 */
    public function exportStatistics(Request $request, FormationRepository $formationRepository, DownloadRepository $downloadRepository, SurveyRepository $surveyRepository, SurveyCommentRepository $surveyCommentRepository)
    {
        if( !$formation = $formationRepository->find($request->get('formation')) )
            return $this->respondNotFound('Formation not found');

        if( !$surveys = $surveyRepository->getStatisticsByFormation($formation) )
            return $this->respondNotFound('Surveys not found for this formation');

        $statistics = [];

        $statistics[strtoupper('Formation')] = [
            "values" => [
                "Theme de l'intervention" => $formation->getTheme(),
                "Format" => $formation->getFormat(),
            ],
            "conditions" => [
                "displayType" => "horizontal",
                "tableWidth" => 2,
            ]
        ];

        foreach ($surveys as $survey) {

            $statistics[strtoupper($survey['group'])]['values'][$survey['question']][] = [
                'answer' => $survey['answer'],
                'countAnswer' => $survey['countAnswers']
            ];

        }

        foreach ($statistics as $key => $statistic) {

            if($key == strtoupper('Formation'))
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

        if( $comments = $surveyCommentRepository->findByFormation($formation) ) {

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

        $file = $this->generateStatisticsSpreadsheet($statistics, 'formation');

	    $download = $downloadRepository->create($request, $file['filepath'], true,  $file['filename']);

        return $this->respondOK($downloadRepository->hydrate($download));
    }
}