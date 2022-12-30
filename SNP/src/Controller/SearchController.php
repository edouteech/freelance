<?php

namespace App\Controller;

use App\Form\Type\SearchType;
use App\Repository\AddressRepository;
use App\Repository\AppendixRepository;
use App\Repository\ContactRepository;
use App\Repository\DocumentRepository;
use App\Repository\FormationCourseRepository;
use App\Repository\NewsRepository;
use App\Repository\PageRepository;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;


/**
 * Search Controller
 *
 * @IsGranted("ROLE_CLIENT")
 *
 * @Security(name="Authorization")

 * @SWG\Tag(name="Misc")
*/
class SearchController extends AbstractController
{
	/**
	 * Perform global search
	 *
	 * @Route("/search", methods={"GET"})
	 *
	 * @SWG\Parameter(name="query", in="query", type="string", description="Search query")
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of results per entity", default=3, maximum=6, minimum=2)
	 * @SWG\Parameter(name="entity[]", in="query", type="string", description="Term", enum={"document","appendix","formation","expert","news"})
	 *
	 * @SWG\Response(response=200, description="Returns a list of resources")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 *
	 * @param DocumentRepository $documentRepository
	 * @param AppendixRepository $appendixRepository
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param AddressRepository $addressRepository
	 * @param NewsRepository $newsRepository
	 * @param ContactRepository $contactRepository
	 * @return JsonResponse
	 */
	public function list(Request $request, DocumentRepository $documentRepository, AppendixRepository $appendixRepository, FormationCourseRepository $formationCourseRepository, AddressRepository $addressRepository, NewsRepository $newsRepository, ContactRepository $contactRepository, PageRepository  $pageRepository)
	{
		$user = $this->getUser();

		$form = $this->submitForm(SearchType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		$result = [];

		//todo: improve each query search
		if( empty($criteria['entity']) || in_array('document', $criteria['entity'])){

			$documents = $documentRepository->query($user, $criteria['limit'], 0, ['search'=>$criteria['query'], 'sort'=>'createdAt', 'order'=>'desc', 'filter'=>'']);
			$result['document'] = [
                'title'=>'Documents',
				'items'=>$documentRepository->hydrateAll($documents),
				'count'=>count($documents)
			];
		}

		if( empty($criteria['entity']) || in_array('appendix', $criteria['entity'])){

			$appendices = $appendixRepository->query($user, $criteria['limit'], 0, ['search'=>$criteria['query'], 'sort'=>'createdAt', 'order'=>'desc', 'filter'=>'']);
			$result['appendix'] = [
                'title'=>'Documents administratifs',
				'items'=>$appendixRepository->hydrateAll($appendices),
				'count'=>count($appendices)
			];
		}

		if( empty($criteria['entity']) || in_array('formation', $criteria['entity'])){

			$formationCourses = $formationCourseRepository->query($user, $criteria['limit'], 0, ['search'=>$criteria['query'], 'seat'=>1, 'sort'=>'createdAt', 'order'=>'desc', 'duration'=>'', 'ethics'=>'', 'discrimination'=>'', 'format'=>'', 'location'=>'', 'theme'=>'', 'formation'=>'']);
			$result['formation'] = [
                'title'=>'Sessions de formation',
				'items'=>$formationCourseRepository->hydrateAll($formationCourses),
				'count'=>count($formationCourses)
			];
		}

		if( empty($criteria['entity']) || in_array('expert', $criteria['entity'])){

			$addresses = $addressRepository->query($user, $criteria['limit'], 0, ['search'=>$criteria['query'], 'sort'=>'createdAt', 'order'=>'desc', 'location'=>'']);

			$experts = [];
			foreach ($addresses as $address){

				$contact = $address->getContact();
				$expert = $contactRepository->hydrate($contact);
				$expert['address'] = $addressRepository->hydrate($address);

				$experts[] = $expert;
			}

			$result['expert'] = [
                'title'=>'Experts',
				'items'=>$experts,
				'count'=>count($experts)
			];
		}

		if( empty($criteria['entity']) || in_array('news', $criteria['entity'])){

			$news = $newsRepository->query($user, $criteria['limit'], 0, ['search'=>$criteria['query'], 'sort'=>'createdAt', 'order'=>'desc']);
			$result['news'] = [
                'title'=>'Actualités',
				'items'=>$newsRepository->hydrateAll($news),
				'count'=>count($news)
			];
		}

        if( empty($criteria['entity']) || in_array('page', $criteria['entity'])){

            $pages = $pageRepository->query($user, $criteria['limit'], 0, ['search'=>$criteria['query'], 'sort'=>'createdAt', 'order'=>'desc']);
            $result['page'] = [
                'title'=>'Pages',
                'items'=>$pageRepository->hydrateAll($pages),
                'count'=>count($pages)
            ];
        }

		return $this->respondOK($result);
	}
}
