<?php

namespace App\Controller;

use App\Entity\FormationFoad;
use App\Form\Type\FormationFoadUpdateType;
use App\Form\Type\FormationReadType;
use App\Form\Type\FormationUpdateType;
use App\Repository\DownloadRepository;
use App\Repository\FormationFoadRepository;
use App\Repository\FormationRepository;
use App\Service\EudonetAction;
use Doctrine\ORM\ORMException;
use Exception;
use Mpdf\Output\Destination;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use App\Service\GoogleDriveService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use App\Entity\Formation;
use Mpdf\Mpdf;

/**
 * Formation Controller
 *
 * @SWG\Tag(name="Formations")

 * @Security(name="Authorization")
*/
class FormationController extends AbstractController
{
	/**
	 * Get all formations
	 *
	 * @IsGranted("ROLE_ADMIN")
	 *
	 * @Route("/formation", methods={"GET"})
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of formations per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 * @SWG\Parameter(name="updatedAt", in="query", type="string")
	 * @SWG\Parameter(name="createdAt", in="query", type="string")
	 *
	 * @SWG\Response(response=200, description="Returns a list of formations")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param FormationRepository $formationRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function list(FormationRepository $formationRepository, Request $request)
	{
		$user = $this->getUser();

		list($limit, $offset) = $this->getPagination($request);

		$form = $this->submitForm(FormationReadType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		$formations = $formationRepository->query($user, $limit, $offset, $criteria);

		return $this->respondOK([
			'items'=>$formationRepository->hydrateAll($formations, $formationRepository::$HYDRATE_FULL),
			'count'=>count($formations),
			'limit'=>$limit,
			'offset'=>$offset
		]);
	}


	/**
	 * Get one formation
	 *
	 * @IsGranted("ROLE_ADMIN")
	 *
	 * @Route("/formation/{id}", methods={"GET"}, requirements={"id"="\d+"})
	 *
	 * @SWG\Response(response=200, description="Returns one formation")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Formation not found")
	 *
	 * @param FormationRepository $formationRepository
	 * @param int $id
	 * @return JsonResponse
	 */
	public function find(FormationRepository $formationRepository, $id)
	{
		if( !$formation = $formationRepository->findOneBy(['id'=>$id]) )
			return $this->respondNotFound('Unable to find formation');

		$formation = $formationRepository->hydrate($formation, $formationRepository::$HYDRATE_FULL);

		return $this->respondOK($formation);
	}


    /**
     * Update formation
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @Route("/formation/{id}", methods={"POST"}, requirements={"id"="\d+"})
     *
     * @SWG\Response(response=200, description="Formation updated")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Formation not found")
     *
     * @param Request $request
     * @param FormationRepository $formationRepository
     * @param FormationFoadRepository $formationFoadRepository
     * @param EudonetAction $eudonetAction
     * @param GoogleDriveService $googleDriveService
     * @param int $id
     * @return JsonResponse
     */
	public function update(Request $request, FormationRepository $formationRepository, FormationFoadRepository $formationFoadRepository, EudonetAction $eudonetAction, GoogleDriveService $googleDriveService, $id)
	{
		if( !$formation = $formationRepository->findOneBy(['id'=>$id]) )
			return $this->respondNotFound('Unable to find formation');

        $objective = $formation->getObjective();

		$form = $this->submitForm(FormationUpdateType::class, $request, $formation);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

        if( $objective != $request->get('objective') )
            $eudonetAction->push($formation);

		if( !$foad = $formationFoadRepository->findOneBy(['formation'=>$formation]) ){

            $foad = new FormationFoad();
            $foad->setFormation($formation);
        }

        $this->submitForm(FormationFoadUpdateType::class, $request, $foad);

        if( !$form->isValid() )
            return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

        $formationFoadRepository->save($foad);

		if( $request->get('updateDoc') ){

			$response = $this->render('e-learning/google-doc.html.twig', ['formation' => $formation, 'foad'=>$foad]);
			$driveFile = $googleDriveService->pushHtmlContent($foad->getDriveFileId(), $formation->getTitle().'.docx', $response->getContent());

			$foad->setDriveFileId($driveFile->getId());
			$formationFoadRepository->save($foad);

			$html = $this->render('pdf/formation.html.twig', ['formation' => $formation, 'foad'=>$foad]);

			$mpdf = new Mpdf(['tempDir' => $this->getParameter('kernel.cache_dir').'/export/tmp']);
			$filename = $this->getPath('formation_directory').'/'.$formation->getId().'.pdf';

			$mpdf->WriteHTML($html);
			$mpdf->Output($filename, Destination::FILE);
		}

        return $this->respondOK([
            'googleDocId' => $foad->getDriveFileId()
        ]);
	}


	/**
	 * Download formation program
	 *
	 * @Route("/formation/{id}/download", methods={"GET"}, requirements={"id"="\d+"})
	 *
	 * @SWG\Response(response=200, description="Formation updated")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Formation not found")
	 *
	 * @param Request $request
	 * @param FormationRepository $formationRepository
	 * @param DownloadRepository $downloadRepository
	 * @param EudonetAction $eudonetAction
	 * @param int $id
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function downloadProgram(Request $request, FormationRepository $formationRepository, DownloadRepository $downloadRepository, EudonetAction $eudonetAction, $id)
	{
		if( !$formation = $formationRepository->findOneBy(['id'=>$id]) )
			return $this->respondNotFound('Unable to find formation');

		if( !$formation->getProgram() )
			return $this->respondNotFound('No program found');

		$url = $eudonetAction->getUrl('formation', $formation->getProgram());

		$path = $this->storeRemoteFile($url);
		$download = $downloadRepository->create($request, $path, false);

		return $this->respondOK($downloadRepository->hydrate($download));
	}



	/**
	 * Get all formation participants
	 *
	 * @Route("/formation/sessions", methods={"GET"})
	 * @SWG\Response(response=200, description="Returns a list of sessions")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param FormationRepository $formationRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function sessions(FormationRepository $formationRepository,Request $request)
	{

		list($limit, $offset) = $this->getPagination($request);


			$formations = $formationRepository->sessionsList($limit, $offset);

				$data = [
					'items'=>$formations,
					'count'=>count($formations),
					'limit'=>$limit,
					'offset'=>$offset
				];
		
			
				return $this->respondOK($data);

	}
}