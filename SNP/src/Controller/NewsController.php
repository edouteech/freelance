<?php

namespace App\Controller;

use App\Form\Type\NewsReadType;
use App\Repository\NewsRepository;
use Doctrine\ORM\ORMException;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Exception;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * News Controller
 *
 * @IsGranted("ROLE_CLIENT")
 * @Security(name="Authorization")

 * @SWG\Tag(name="News")
*/
class NewsController extends AbstractController
{
	/**
	 * Get all news
	 *
	 * @Route("/news", methods={"GET"})
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of news per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 * @SWG\Parameter(name="sort", in="query", type="string", description="Order result", default="createdAt", enum={"popular","createdAt","rateAverage"})
	 * @SWG\Parameter(name="target", in="query", type="string", description="Target", default="all", enum={"all","app","extranet"})
	 * @SWG\Parameter(name="order", in="query", type="string", description="Order result", default="desc", enum={"asc", "desc"})
	 *
	 * @SWG\Response(response=200, description="Returns a list of news")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param NewsRepository $newsRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 */
	public function list(NewsRepository $newsRepository, Request $request)
	{
		$user = $this->getUser();

		list($limit, $offset) = $this->getPagination($request);

		$form = $this->submitForm(NewsReadType::class, $request);
		$criteria = $form->getData();

		$news = $newsRepository->query($user, $limit, $offset, $criteria);

		return $this->respondOK([
			'items'=>$newsRepository->hydrateAll($news),
			'count'=>count($news),
			'limit'=>$limit,
			'offset'=>$offset
		]);
	}


    /**
     * Get one news
     *
     * @Route("/news/{id}", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Returns a news")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param $id
     * @param NewsRepository $newsRepository
     * @return JsonResponse
     * @throws ORMException
     * @throws ExceptionInterface
     */
	public function find($id, NewsRepository $newsRepository)
	{
		$user = $this->getUser();

		if( is_numeric($id) )
			$news = $newsRepository->findOneByUser($id, $user);
		else
			$news = $newsRepository->findOneBy(['slug'=>$id, 'status'=>'publish', 'role'=>$user->getRoles()]);

		if( !$news )
			return $this->respondNotFound('News not found');

		$data = $newsRepository->hydrate($news, $newsRepository::$HYDRATE_FULL);

		try {

			$filePath = $this->storeRemoteFile($news->getRemoteUrl(), false, $news->getUpdatedAt(), '/edito');
			$response = json_decode(file_get_contents($filePath), true);

			$data['layout'] = $response['layout']??false;

		} catch (Exception $e) {

			return $this->respondInternalServerError($e->getMessage());
		}

		return $this->respondOK($data);
	}
}
