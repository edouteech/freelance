<?php

namespace App\Controller;

use App\Repository\DownloadRepository;
use App\Repository\UserRepository;
use App\Service\SnpiConnector;
use Doctrine\ORM\ORMException;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * Signature Collection Controller
 *
 * @SWG\Tag(name="Signature collection")

 * @IsGranted("ROLE_SIGNATURE")
 *
 * @Security(name="Authorization")
 *
 */
class SignatureCollectionController extends AbstractController
{

	/**
	 * Get all signatures collections
	 *
	 * @Route("/collection/signature", methods={"GET"})
	 *
	 * @SWG\Parameter(name="filter", in="query", type="integer")
	 * @SWG\Parameter(name="limit", in="query", type="integer")
	 * @SWG\Parameter(name="offset", in="query", type="integer")
	 *
	 * @SWG\Response(response=200, description="List of signatures collection")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param SnpiConnector $snpiConnector
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function list(Request $request, SnpiConnector $snpiConnector)
	{
		$user = $this->getUser();
        list($limit, $offset) = $this->getPagination($request);

		$output = $snpiConnector->list(['num_adherent'=>$user->getMemberId(), 'page'=>$offset/$limit, 'limit'=>$limit, 'filter'=>$request->get('filter')]);

		return $this->respondOK($output);
	}


	/**
	 * Create signature collections
	 *
	 * @Route("/collection/signature", methods={"POST"})
	 *
	 * @SWG\Response(response=201, description="Collection created")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param SnpiConnector $snpiConnector
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function create(Request $request, SnpiConnector $snpiConnector)
	{
		$user = $this->getUser();

		$data = $request->request->all();

		if( empty($data) )
			return $this->respondError('Empty form data');

		$data['num_adherent'] = $user->getMemberId();
		$data['action'] = 'post';

		/** @var UploadedFile[] $files */
		$files = $request->files->all();

		foreach($files as $key=>$file ){

			if( is_array($file) ){

				/** @var UploadedFile[] $file */
				foreach ($file as $index=>$_file ){

					if( $_file && $_file->getPathname() ){

						$data[$key.'['.$index.']'] = DataPart::fromPath($_file->getPathname(), $this->sanitizeFilename($_file->getClientOriginalName()), $_file->getClientMimeType());
						$data[$key.'_debug['.$index.']'] = $data[$key.'['.$index.']']->asDebugString();
					}
				}
			}
			else{

				if( $file && $file->getPathname() ){

					$data[$key] = DataPart::fromPath($file->getPathname(), $this->sanitizeFilename($file->getClientOriginalName()), $file->getClientMimeType());
					$data[$key.'_debug'] = $data[$key]->asDebugString();
				}
			}
		}

		$output = $snpiConnector->create($data);

		return $this->respondOK($output);
	}

	/**
	 * Get signature packs
	 *
	 * @Route("/collection/signature/pack", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="List of signatures collection")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param SnpiConnector $snpiConnector
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function getPacks(SnpiConnector $snpiConnector)
	{
		$output = $snpiConnector->getPacks();

		return $this->respondOK($output);
	}

    /**
     * Resend link
     *
     * @Route("/collection/signature/{id}/link", methods={"POST"})
     *
     * @SWG\Response(response=200, description="Link sent")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param SnpiConnector $snpiConnector
     * @param $id
     * @return JsonResponse
     * @throws Exception
     */
	public function sendLink(SnpiConnector $snpiConnector, $id)
	{
		$user = $this->getUser();

		$output = $snpiConnector->resendLink(['num_adherent'=>$user->getMemberId(), 'id'=>$id]);

		return $this->respondOK($output);
	}

	/**
	 * Get signatures stock
	 *
	 * @Route("/collection/signature/stock", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Return signatures stock")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param SnpiConnector $snpiConnector
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function getStock(SnpiConnector $snpiConnector)
	{
		$user = $this->getUser();

		$output = $snpiConnector->getStock(['num_adherent'=>$user->getMemberId()]);

		return $this->respondOK($output);
	}

	/**
	 * Update signatures stock
	 *
	 * @Route("/collection/signature/stock", methods={"POST"})
	 *
	 * @SWG\Parameter(name="variation", in="body", type="integer", @SWG\Schema())
	 * @SWG\Parameter(name="memberId", in="body", type="string", @SWG\Schema())
	 *
	 * @IsGranted("ROLE_ADMIN")
	 * @Security(name="Authorization")
	 *
	 * @SWG\Response(response=200, description="Return signatures stock")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param SnpiConnector $snpiConnector
	 * @param UserRepository $userRepository
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function updateStock(Request $request, SnpiConnector $snpiConnector, UserRepository $userRepository)
	{
		$output = $snpiConnector->updateStock(['variation'=>intval($request->get('variation')), 'num_adherent'=>$request->get('memberId')]);

		return $this->respondOK($output);
	}


	/**
	 * Download contract
	 *
	 * @Route("/collection/signature/{id}/download", methods={"GET"})
	 *
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param DownloadRepository $downloadRepository
	 * @param SnpiConnector $snpiConnector
	 * @param $id
	 * @return Response
	 * @throws ORMException
	 * @throws ExceptionInterface
	 * @throws Exception
	 */
	public function download(Request $request, DownloadRepository $downloadRepository, SnpiConnector $snpiConnector, $id)
	{
		$user = $this->getUser();

		$url = $snpiConnector->getDownloadUrl(['num_adherent'=>$user->getMemberId(), 'id'=>$id]);

        $path = $this->storeRemoteFile($url);
        $download = $downloadRepository->create($request, $path, true);

        return $this->respondOK($downloadRepository->hydrate($download));
	}


	/**
	 * Refresh contract
	 *
	 * @Route("/collection/signature/{id}/refresh", methods={"GET"})
	 *
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param SnpiConnector $snpiConnector
	 * @param $id
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function refresh(SnpiConnector $snpiConnector, $id)
	{
		$user = $this->getUser();

		$output = $snpiConnector->refresh(['num_adherent'=>$user->getMemberId(), 'id'=>$id]);

		return $this->respondOK($output);
	}


	/**
	 * Cancel signatures collection
	 *
	 * @Route("/collection/signature/{id}/cancel", methods={"GET"})
	 *
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="User not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param SnpiConnector $snpiConnector
	 * @param $id
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function cancel(SnpiConnector $snpiConnector, $id)
	{
		$user = $this->getUser();

		$output = $snpiConnector->cancel(['num_adherent'=>$user->getMemberId(), 'id'=>$id]);

		return $this->respondOK($output);
	}


	/**
	 * Check signature file
	 *
	 * @Route("/collection/signature/file/check", methods={"POST"})
	 *
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="User not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param SnpiConnector $snpiConnector
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function checkFile(Request $request, SnpiConnector $snpiConnector)
	{
		$data = [];

		/** @var UploadedFile[] $files */
		$files = $request->files->all();

		foreach($files as $key=>$file ){

			if( $file && $file->getPathname() ){

				$data['file_upload'] = DataPart::fromPath($file->getPathname(), $this->sanitizeFilename($file->getClientOriginalName()), $file->getClientMimeType());
				$data[$key.'_debug'] = $data[$key]->asDebugString();

				break;
			}
		}

		if( empty($data) )
			return $this->respondError('Empty form data');

		try {

			$output = $snpiConnector->checkFile($data);
			return $this->respondOK($output);

		} catch (Exception $e) {

			if( $e->getMessage() == 'ko')
				return $this->respondError('File not allowed');
			else
				throw $e;
		}
	}
}
