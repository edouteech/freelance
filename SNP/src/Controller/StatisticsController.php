<?php

namespace App\Controller;

use App\Repository\ContactRepository;
use App\Repository\FormationParticipantRepository;
use App\Repository\PaymentRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Nelmio\ApiDocBundle\Annotation\Security;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Rating Controller
 *
 * @SWG\Tag(name="User rating")
 *
 * @Security(name="Authorization")
 *
 * @IsGranted("ROLE_ADMIN")
 */
class StatisticsController extends AbstractController
{

	/**
	 * Get statistics
	 *
	 * @Route("/statistics", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Statistics sent")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @return JsonResponse
	 */
	public function getStats(PaymentRepository $paymentRepository, ContactRepository $contactRepository, FormationParticipantRepository $formationParticipantRepository, Request $request)
	{

		if(!empty($request->query->get('startAt')) && !empty($request->query->get('endAt')))
		{
			$today = (new \DateTime($request->query->get('endAt')))->setTime(0,0,0);
			$firstDayThisMonth = (new \DateTime($request->query->get('startAt')))->setTime(0,0,0);
			$firstDayLastMonth = (new \DateTime($firstDayThisMonth))->modify('-1 month')->setTime(0,0,0);
			$oneYearAgo = (new \DateTime($today))->modify('-1 year')->setTime(0,0,0);
		}else {
			$today = (new \DateTime());
			$firstDayThisMonth = (new \DateTime('first day of this month'))->setTime(0,0,0);
			$firstDayLastMonth = (new \DateTime('first day of last month'))->setTime(0,0,0);
			$oneYearAgo = (new \DateTime())->modify('-1 year')->setTime(0,0,0);
		}

		return $this->respondOK([
			'sales'=>[
				'current' => $paymentRepository->getSales($firstDayThisMonth, $today),
				'compare' => $paymentRepository->getSales($firstDayLastMonth, $firstDayThisMonth),
				'monthly' => $paymentRepository->getSalesbyMonths($oneYearAgo, $today)
			],
			'registered'=>[
				'current' => $contactRepository->countRegistered($firstDayThisMonth, $today),
				'compare' => $contactRepository->countRegistered($firstDayLastMonth, $firstDayThisMonth)
			],
			'formationParticipant'=>[
				'registered'=> [
					'current' => $formationParticipantRepository->countRegistered($firstDayThisMonth, $today),
					'compare' => $formationParticipantRepository->countRegistered($firstDayLastMonth, $firstDayThisMonth)
				],
				'present'=> [
					'current' => $formationParticipantRepository->countPresent($firstDayThisMonth, $today),
					'compare' => $formationParticipantRepository->countPresent($firstDayLastMonth, $firstDayThisMonth)
				]
			]
		]);
	}
}
