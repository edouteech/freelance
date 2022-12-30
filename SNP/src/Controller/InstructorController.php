<?php

namespace App\Controller;

use App\Form\Type\InstructorReadType;
use App\Repository\InstructorRepository;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;


/**
 * Instructor Controller
 *
 * @SWG\Tag(name="Instructor")

 * @Security(name="Authorization")
*/
class InstructorController extends AbstractController
{
	/**
	 * Get all instructors
	 *
	 * @IsGranted("ROLE_ADMIN")
	 *
	 * @Route("/instructor", methods={"GET"})
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of instructors per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 * @SWG\Parameter(name="updatedAt", in="query", type="string")
	 * @SWG\Parameter(name="createdAt", in="query", type="string")
	 *
	 * @SWG\Response(response=200, description="Returns a list of instructors")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param InstructorRepository $instructorRepository
	 * @param Request $request
	 *
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function list(InstructorRepository $instructorRepository, Request $request)
	{
		$user = $this->getUser();

		list($limit, $offset) = $this->getPagination($request);

		$form = $this->submitForm(InstructorReadType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		$instructors = $instructorRepository->query($user, $limit, $offset, $criteria);

		return $this->respondOK([
			'items'=>$instructorRepository->hydrateAll($instructors, $instructorRepository::$HYDRATE_FULL),
			'count'=>count($instructors),
			'limit'=>$limit,
			'offset'=>$offset
		]);
	}
}