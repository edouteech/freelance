<?php

namespace App\Controller;


use App\Form\Type\DocumentReadType;
use App\Repository\DocumentRepository;
use App\Repository\DownloadRepository;
use App\Repository\ContactMetadataRepository;
use Doctrine\ORM\ORMException;
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
 * Document Controller
 *
 * @IsGranted("ROLE_CLIENT")
 *
 * @SWG\Tag(name="Documents")
 * @Security(name="Authorization")
*/
class DocumentController extends AbstractController
{
	/**
	 * Get all documents
	 *
	 * @Route("/document", methods={"GET"})
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of documents per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 * @SWG\Parameter(name="filter", in="query", type="string", description="Filter result", enum={"favorite","year"})
	 * @SWG\Parameter(name="year", in="query", type="integer", description="Filter by year")
	 * @SWG\Parameter(name="sort", in="query", type="string", description="Order result", default="updatedAt", enum={"popular","updatedAt","createdAt","category"})
	 * @SWG\Parameter(name="order", in="query", type="string", description="Order result", default="desc", enum={"asc", "desc"})
	 * @SWG\Parameter(name="search", in="query", type="string", description="Query")
	 * @SWG\Parameter(name="category[]", in="query", type="string", description="Terms")
	 *
	 * @SWG\Response(response=200, description="Returns a list of documents")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param DocumentRepository $documentRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 */
	public function list(DocumentRepository $documentRepository, Request $request)
	{
		$user = $this->getUser();

		list($limit, $offset) = $this->getPagination($request);

		$form = $this->submitForm(DocumentReadType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		$documents = $documentRepository->query($user, $limit, $offset, $criteria);

		return $this->respondOK([
			'items'=>$documentRepository->hydrateAll($documents),
			'count'=>count($documents),
			'limit'=>$limit,
			'offset'=>$offset
		]);
	}


	/**
	 * Get one document
	 *
	 * @Route("/document/{id}", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Returns one document")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Document not found")
	 *
	 * @param DocumentRepository $documentRepository
	 * @param $id
	 *
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function find(DocumentRepository $documentRepository, $id)
	{
		$user = $this->getUser();

		if( !$document = $documentRepository->findOneByUserRole($id, $user) )
			return $this->respondNotFound('Unable to find document');

		return $this->respondOK($documentRepository->hydrate($document, $documentRepository::$HYDRATE_FULL));
	}


    /**
     * Download document asset
     *
     * @Route("/document/{id}/download", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @SWG\Parameter(name="asset", in="query", type="integer", description="Asset position", default=1, minimum=1)
     *
     * @SWG\Response(response=200, description="Redirect to document")
     * @SWG\Response(response=500, description="Internal server error")
     * @SWG\Response(response=404, description="Document/Asset not found")
     *
     * @param DocumentRepository $documentRepository
     * @param DownloadRepository $downloadRepository
     * @param ContactMetadataRepository $metadataRepository
     * @param Request $request
     * @param int $id
     *
     * @return Response
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws Exception
     */
	public function download(DocumentRepository $documentRepository, DownloadRepository $downloadRepository, ContactMetadataRepository $metadataRepository, Request $request, $id)
	{
		$user = $this->getUser();

		if( !$document = $documentRepository->findOneByUserRole($id, $user) )
			return $this->respondNotFound('Unable to find document');

		$position = $request->get('asset', 1);

		if( $asset = $document->getAsset($position) ){

			if( $url = $asset->getUrl() ){

				if( $contact = $user->getContact() ){

					$criteria = ['state'=>'read', 'contact'=>$contact, 'entityId'=>$document->getId(), 'type'=>'resource'];
					$metadataRepository->save($criteria);
				}

                $path = $this->storeRemoteFile($url, false, $asset->getModifiedAt(), '/uploads');
                $download = $downloadRepository->create($request, $path, false);

				return $this->respondOK($downloadRepository->hydrate($download));
			}
		}

		return $this->respondNotFound('Unable to find document asset');
	}
}
