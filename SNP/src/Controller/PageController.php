<?php

namespace App\Controller;

use App\Repository\PageRepository;
use Doctrine\ORM\ORMException;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * Page Controller
 *
 * @IsGranted("ROLE_CLIENT")
 * @Security(name="Authorization")

 * @SWG\Tag(name="Pages")
*/
class PageController extends AbstractController
{
	/**
	 * Get all pages
	 *
	 * @Route("/page", methods={"GET"})
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of pages per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 *
	 * @SWG\Response(response=200, description="Returns a list of pages")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param PageRepository $pageRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 */
	public function list(PageRepository $pageRepository, Request $request)
	{
		$user = $this->getUser();

		$limit = max(2, min(100, intval($request->get('limit', $_ENV['DEFAULT_LIMIT']??10))));
		$offset = max(0, intval($request->get('offset', 0)));

		$pages = $pageRepository->query($user, $limit, $offset, ['sort'=>'createdAt', 'order'=>'ASC']);

		return $this->respondOK([
			'items'=>$pageRepository->hydrateAll($pages),
			'count'=>count($pages),
			'limit'=>$limit,
			'offset'=>$offset
		]);
	}


    /**
     * Get one page
     *
     * @Route("/page/{id}", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Returns a page object")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param PageRepository $pageRepository
     * @param $id
     * @return JsonResponse
     * @throws ORMException
     * @throws ExceptionInterface
     */
	public function find(PageRepository $pageRepository, $id)
	{
		$user = $this->getUser();

		if( is_numeric($id) )
			$page = $pageRepository->findOneByUser($id, $user);
		else
			$page = $pageRepository->findOneBy(['slug'=>$id, 'status'=>'publish', 'role'=>$user->getRoles()]);

		if( !$page )
			return $this->respondNotFound('Page not found');

		$data = $pageRepository->hydrate($page, $pageRepository::$HYDRATE_FULL);

		return $this->respondOK($data);
	}
}
