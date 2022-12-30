<?php

namespace App\Controller;

use App\Repository\AppendixRepository;
use App\Repository\TermRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Nelmio\ApiDocBundle\Annotation\Security;

/**
 * Term Controller
 *
 * @SWG\Tag(name="Misc")
 *
 * @IsGranted("ROLE_CLIENT")
 * @Security(name="Authorization")
 */
class TermController extends AbstractController
{
	/**
	 * Get all terms
	 *
	 * @Route("/term", methods={"GET"})
	 *
	 * @SWG\Parameter(name="hierarchical", in="query", type="boolean", description="", default=1)
	 * @SWG\Parameter(name="taxonomy", in="query", type="string", description="", default="all", enum={"all","category","tag","administrative"})
     *
	 * @SWG\Response(response=200, description="Returns terms")
	 * @SWG\Response(response=500, description="Internal server error")
	 * @SWG\Response(response=404, description="Terms not found")
	 *
	 * @param TermRepository $termRepository
	 *
	 * @param AppendixRepository $appendixRepository
	 * @param Request $request
	 * @return JsonResponse
	 */
	public function getTerms(TermRepository $termRepository, AppendixRepository $appendixRepository, Request $request)
	{
		$user = $this->getUser();

		$taxonomy = $request->get('taxonomy');
		$terms = [];

		$criteria = [
			'sort' => 'title',
			'order' => 'asc'
		];

		if( $taxonomy != 'administrative') {

			if( $taxonomy )
				$criteria['taxonomies'] = [$taxonomy];

			if( $taxonomy == 'all' )
				$criteria['taxonomies'] = ['category'];

			$documentTerms = $termRepository->query($user, $criteria);

			if( $documentTerms ){

				$type = $request->get('hierarchical', 1) ? $termRepository::$HYDRATE_HIERARCHICAL : $termRepository::$HYDRATE_FULL;
				$terms = array_merge($terms, $termRepository->hydrateAll($documentTerms, $type));
			}
		}

		if( !$taxonomy || $taxonomy == 'administrative' || $taxonomy == 'all') {

			//workaround to merge appendix abstract category

			$appendixTerms = $appendixRepository->getTerms($user);

			if( $appendixTerms ){

				$appendixTerms = [
					'id'=>999900,
					'title'=>'Documents administratifs',
					'slug'=>'documents-administratifs',
					'taxonomy'=>'administrative',
					'children'=>$termRepository->hydrateAll($appendixTerms, $termRepository::$HYDRATE_FULL)
				];

				$terms = array_merge($terms, [$appendixTerms]);
			}
		}

		return $this->respondOK([
			'count'=>count($terms),
			'items'=>$terms
		]);
	}
}
