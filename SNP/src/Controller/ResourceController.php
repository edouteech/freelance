<?php

namespace App\Controller;

use App\Form\Type\ResourceReadType;
use App\Repository\ResourceRepository;
use Doctrine\DBAL\DBALException;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;


/**
 * Resource Controller
 *
 * @IsGranted("ROLE_CLIENT")
 *
 * @SWG\Tag(name="Documents")
 * @Security(name="Authorization")
*/
class ResourceController extends AbstractController
{
	/**
	 * Get last mixed resources
	 *
	 * @Route("/resource", methods={"GET"})
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of documents per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 *
	 * @SWG\Response(response=200, description="Returns a list of resources")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 *
	 * @param ResourceRepository $resourceRepository
	 * @return JsonResponse
	 * @throws DBALException
	 */
	public function list(Request $request, ResourceRepository $resourceRepository)
	{
		$user = $this->getUser();

		$form = $this->submitForm(ResourceReadType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		list($limit, $offset) = $this->getPagination($request);
		list($items, $count) = $resourceRepository->query($user, $limit, $offset, $criteria);

		return $this->respondOK([
			'items'=>$this->hydrateAll($items),
			'count'=>$count,
			'limit'=>$limit,
			'offset'=>$offset
		]);
	}
}
