<?php

namespace App\Controller;

use App\Entity\Rating;
use App\Repository\DocumentRepository;
use App\Repository\FormationCourseRepository;
use App\Repository\NewsRepository;
use App\Repository\PageRepository;
use App\Repository\RatingRepository;
use App\Repository\ResourceRepository;
use App\Service\Mailer;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Nelmio\ApiDocBundle\Annotation\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Rating Controller
 *
 * @SWG\Tag(name="User rating")
 *
 * @Security(name="Authorization")
 * @IsGranted("ROLE_CLIENT")
 */
class RateController extends AbstractController
{

	/**
	 * Rating list
	 *
	 * @Route("/rate", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Rating sent")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param RatingRepository $ratingRepository
	 * @return JsonResponse
	 */
	public function getAllRatings(RatingRepository $ratingRepository)
	{
		$user = $this->getUser();

        $ratings = $ratingRepository->findBy(['user'=>$user]);

        $data = [];

        foreach ($ratings as $rating)
            $data[] = $rating->getResource()->getId();

		return $this->respondOK(['resources'=>$data]);
	}

    /**
     * Rating list
     *
     * @Route("/rate/comments", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Rating sent")
     * @SWG\Response(response=500, description="Internal server error")
     * @IsGranted("ROLE_ADMIN")
     * @Security(name="Authorization")
     *
     * @param RatingRepository $ratingRepository
     * @param Request $request
     * @return JsonResponse
     */
	public function getRatingComments(RatingRepository $ratingRepository, Request $request)
	{
        list($limit, $offset) = $this->getPagination($request);

        $ratings = $ratingRepository->query($limit, $offset, ['hasComment'=>true, 'sort'=>'id', 'order'=>'DESC']);

        return $this->respondOK([
            'items'=>$ratingRepository->hydrateAll($ratings),
            'count'=>count($ratings),
            'limit'=>$limit,
            'offset'=>$offset
        ]);
	}


    /**
     * Rate resource
     *
     * @Route("/rate/{type}/{id}", methods={"POST"})
     *
     * @SWG\Response(response=200, description="Rating sent")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param $type
     * @param $id
     * @param Request $request
     * @param FormationCourseRepository $formationCourseRepository
     * @param NewsRepository $newsRepository
     * @param PageRepository $pageRepository
     * @param DocumentRepository $documentRepository
     * @param ResourceRepository $resourceRepository
     * @param RatingRepository $ratingRepository
     * @param Mailer $mailer
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws NonUniqueResultException
     */
	public function doRating($type, $id, Request $request, FormationCourseRepository $formationCourseRepository, NewsRepository $newsRepository, PageRepository $pageRepository, DocumentRepository $documentRepository, ResourceRepository $resourceRepository, RatingRepository $ratingRepository, Mailer $mailer)
	{
		$user = $this->getUser();
        $resource = $resourceRepository->findOneBy(['id'=>$id]);

		if( !$resource || $resource->getType() != $type )
            return $this->respondNotFound('Resource not found');

		if( !$rating = $ratingRepository->findOneBy(['resource'=>$resource, 'user'=>$user]) ) {

			$rating = new Rating();
			$rating->setUser($user);
			$rating->setResource($resource);
		}

		$rating->setRate($request->get('rating'));
		$rating->setComment($request->get('comment'));

		$ratingRepository->save($rating);

        $averageRatings = $resource->getAverageRatings();
        $resource->setAverageRate($averageRatings);
        $resourceRepository->save($resource);

        if( strlen($rating->getComment()) > 10 ){

            switch ( $resource->getType() ){

                case 'document':

                    if( !$document = $documentRepository->findOneByUserRole($resource->getId(), $user) )
                        return $this->respondNotFound('Document not found');

                    $title = $document->getTitle();
                    $link = $document->getDashboardLink();
                    $type = 'Le document';

                    break;

                case 'page':

                    if( !$page = $pageRepository->findOneByUser($resource->getId(), $user) )
                        return $this->respondNotFound('Page not found');

                    $title = $page->getTitle();
                    $link = $page->getDashboardLink();
                    $type = 'La page';

                    break;

                case 'news':

                    if( !$news = $newsRepository->findOneByUser($resource->getId(), $user) )
                        return $this->respondNotFound('News not found');

                    $title = $news->getTitle();
                    $link = $news->getDashboardLink();
                    $type = "L'actualité";

                    break;

                case 'formation':

                    if( !$formationCourse = $formationCourseRepository->find($resource->getId()) )
                        return $this->respondNotFound('Formation course not found');

                    $title = $formationCourse->getFormation()->getTitle();
                    $link = $_ENV['DASHBOARD_URL'].'/formations/formation/'.$formationCourse->getId();
                    $type = "La formation";

                    break;

                default:
                    return $this->respondBadRequest('Invalid arguments');
            }

            $html  = 'Member Id : '.$user->getMemberId().'<br/>';
            $html .= 'Email : '.$user->getEmail().'<br/>';
            $html .= 'Link : '.$link.'<br/><br/>';
            $html .= $type.' '.$title.': <br/>'.$rating->getComment();


            $mailer->sendMessage($_ENV['MAILER_TO'], 'Nouveau commentaire', $html);
        }

		return $this->respondOK([
            'rating'=>$rating->getRate(),
            'average'=>$resource->getAverageRatings(),
            'count'=>count($resource->getRatings())
        ]);
	}
}
