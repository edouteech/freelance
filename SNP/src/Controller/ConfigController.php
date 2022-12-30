<?php

namespace App\Controller;

use App\Repository\FormationCourseRepository;
use App\Repository\MenuRepository;
use App\Repository\OptionRepository;
use App\Repository\ResourceRepository;
use App\Repository\TermRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;


/**
 * Config Controller
 *
 * @SWG\Tag(name="Misc")
*/
class ConfigController extends AbstractController
{
	/**
	 * Get options and menu
	 *
	 * @Route("/config", methods={"GET"})
	 *
	 * @SWG\Parameter(name="property[]", in="query", type="string", description="Property", default="null", enum={"option","menu","e-signatures","registration","news","formations","documents"})
	 *
	 * @SWG\Response(response=200, description="Returns a list of options and menus")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param OptionRepository $optionRepository
	 * @param MenuRepository $menuRepository
	 * @param ResourceRepository $resourceRepository
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param TermRepository $termRepository
	 * @return JsonResponse
	 */
	public function list(Request $request, OptionRepository $optionRepository, MenuRepository $menuRepository, ResourceRepository $resourceRepository, FormationCourseRepository $formationCourseRepository, TermRepository $termRepository)
	{
		$options = $optionRepository->findBy(['public'=>1]);
		$menus = $menuRepository->findBy([], ['menuOrder'=>'ASC']);
		$maintenance = $optionRepository->get('maintenance');

		$properties = $request->get('property');

		$now = new \DateTime();

		$config = [
			'timestamp'=>$now->getTimestamp()*1000,
			'maintenance'=>[
				'enabled'=> ($maintenance['activate']??false) != 'none',
				'status'=> $maintenance['text']??false
			]
		];

		if( empty($properties) || in_array('option', $properties) )
			$config['option'] = $optionRepository->hydrateAll($options);

		if( empty($properties) || in_array('menu', $properties) )
			$config['menu'] = $menuRepository->hydrateAll($menus);

		if( empty($properties) || in_array('formations', $properties) )
			$config['formations'] = [
				'enabled'=>intval($_ENV['TRAINING_IN_HOUSE_ENABLED']??0)||intval($_ENV['TRAINING_WEBINAR_ENABLED']??0)||intval($_ENV['TRAINING_INSTRUCTOR_LED_ENABLED']??0)||intval($_ENV['TRAINING_ELEARNING_ENABLED']??0),
				'filters'=>$formationCourseRepository->getFilters(null, ['search'=>'', 'seat'=>0, 'sort'=>'createdAt', 'order'=>'desc', 'duration'=>'', 'ethics'=>'', 'discrimination'=>'', 'format'=>'', 'location'=>'', 'theme'=>'', 'formation'=>''])
			];

		if( empty($properties) || in_array('documents', $properties) )
			$config['documents'] = [
				'enabled'=> intval($_ENV['DOCUMENTS_ENABLED']??0),
				'years'=> $resourceRepository->getYears()
			];

		if( empty($properties) || in_array('news', $properties) )
			$config['news'] = [
				'enabled'=> true,
				'categories'=> $termRepository->hydrateAll($termRepository->findBy(['taxonomy'=>'news_category']))
			];

		if( empty($properties) || in_array('e-signatures', $properties) )
			$config['e-signatures'] = [
				'enabled'=> intval($_ENV['SIGNATURES_ENABLED']??0)
			];

		if( empty($properties) || in_array('registration', $properties) )
			$config['registration'] = [
				'enabled'=> intval($_ENV['CACI_REGISTRATION']??0),
				'insurances'=> intval($_ENV['CACI_INSURANCES']??0)
			];

		return $this->respondOK($config);
	}
}