<?php

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Form\Type\NotificationType;
use App\Repository\AppendixRepository;
use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Repository\DocumentRepository;
use App\Repository\FormationCourseRepository;
use App\Repository\NewsRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\SnpiConnector;
use DateTimeInterface;
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
 * Notification Controller
 *
 * @SWG\Tag(name="User notification")
 *
 * @Security(name="Authorization")
 * @IsGranted("ROLE_CLIENT")
 */
class NotificationController extends AbstractController
{
	/**
	 * Update user notification read datetime
	 *
	 * @Route("/user/notification", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="Notification read time updated")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param UserRepository $userRepository
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function updateNotification(UserRepository $userRepository)
	{
		$user = $this->getUser();

		$user->setNotifiedAt('now');
		$userRepository->save($user);

		return $this->respondOK();
	}


	/**
	 * Get user notifications
	 *
	 * @Route("/user/notification", methods={"GET"})
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of results per entity", default=3, maximum=6, minimum=2)
	 * @SWG\Parameter(name="entity[]", in="query", type="string", description="Term", enum={"document","appendix","formation","news","signature","checkup"})
	 *
	 * @SWG\Response(response=200, description="Notifications list")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param NewsRepository $newsRepository
	 * @param DocumentRepository $documentRepository
	 * @param SnpiConnector $snpiConnector
	 * @param AppendixRepository $appendixRepository
	 * @param FormationCourseRepository $formationCourseRepository
	 * @param CompanyRepository $companyRepository
	 * @param ContactRepository $contactRepository
	 * @param Request $request
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function getNotifications(NewsRepository $newsRepository, DocumentRepository $documentRepository, SnpiConnector $snpiConnector, AppendixRepository $appendixRepository, FormationCourseRepository $formationCourseRepository, CompanyRepository $companyRepository, ContactRepository $contactRepository, Request $request)
	{
		$user = $this->getUser();

		$items = [];

		$result = [
			'items'=>[],
			'count'=>0
		];

		if( $user->hasNotification() ){

			/** @var DateTimeInterface $lastLoginAt */
			$lastLoginAt = $user->getLastLoginAt();

			/** @var DateTimeInterface $notifiedAt */
			$notifiedAt = $user->getNotifiedAt();

			$form = $this->submitForm(NotificationType::class, $request);

			if( !$form->isValid() )
				return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

			$criteria = $form->getData();

			if( empty($criteria['entity']) || in_array('document', $criteria['entity'])){

				$documents = $documentRepository->query($user, $criteria['limit'], 0, ['createdAt'=>$lastLoginAt, 'sort'=>'createdAt', 'order'=>'desc', 'filter'=>'']);

				foreach ($documents as $document){
					if( $document->getCreatedAt() > $notifiedAt )
						$result['count']++;
				}

				$items['documents'] = $documentRepository->hydrateAll($documents);
			}

			if( empty($criteria['entity']) || in_array('news', $criteria['entity'])){

				$news = $newsRepository->query($user, $criteria['limit'], 0, ['createdAt'=>$lastLoginAt, 'sort'=>'createdAt', 'order'=>'desc']);

				foreach ($news as $_news){
					if( $_news->getCreatedAt() > $notifiedAt )
						$result['count']++;
				}

				$items['news'] = $newsRepository->hydrateAll($news);
			}

			if( empty($criteria['entity']) || in_array('appendix', $criteria['entity'])){

				$appendices = $appendixRepository->query($user, $criteria['limit'], 0, ['createdAt'=>$lastLoginAt, 'sort'=>'createdAt', 'order'=>'desc', 'filter'=>'']);

				foreach ($appendices as $appendix){
					if( $appendix->getCreatedAt() > $notifiedAt )
						$result['count']++;
				}

				$items['appendix'] = $appendixRepository->hydrateAll($appendices);
			}

			if( empty($criteria['entity']) || in_array('formation', $criteria['entity'])){

				$formationCourses = $formationCourseRepository->query($user, $criteria['limit'], 0, ['createdAt'=>$lastLoginAt, 'seat'=>1, 'sort'=>'createdAt', 'order'=>'desc', 'duration'=>'', 'ethics'=>'',  'discrimination'=>'', 'format'=>'', 'location'=>'']);

				foreach ($formationCourses as $formationCourse){
					if( $formationCourse->getCreatedAt() > $notifiedAt )
						$result['count']++;
				}

				$items['formation'] = $formationCourseRepository->hydrateAll($formationCourses);
			}

			if( (empty($criteria['entity']) || in_array('signature', $criteria['entity'])) && $memberId = $user->getMemberId()){

				$output = $snpiConnector->list(['num_adherent'=>$memberId, 'modifiedAt'=>$lastLoginAt->format('c'), 'limit'=>$criteria['limit']]);

				foreach ($output['items'] as $signature){
					if( $signature['modifiedAt'] > $notifiedAt->getTimestamp() )
						$result['count']++;
				}

				$items['signature'] = array_slice($output['items'], 0, $criteria['limit']);
			}
		}

		if( empty($criteria['entity']) || in_array('checkup', $criteria['entity'])){

			$company = $user->getCompany();
			$contact = $user->getContact();

			if( $user->isLegalRepresentative() ){

				$checkup = $companyRepository->checkup($company);

				if( $checkup['count'] ){

					$items['checkup'] = $checkup['data'];
					$result['count'] += $checkup['count'];
				}
			}
			else{

				$checkup = $contactRepository->checkup($contact, $company);

				if( $checkup['count'] ){

					$items['checkup'] = [$checkup['data']];
					$result['count'] ++;
				}
			}
		}

		foreach ($items as $type=>$_items)
			$result['items'] = array_merge($result['items'], $_items);

		usort($result['items'], function($a, $b){

			$datea = $a['type'] == 'signature' ? $a['modifiedAt'] : ($a['createdAt']);
			$dateb = $b['type'] == 'signature' ? $b['modifiedAt'] : ($b['createdAt']);

			return $datea < $dateb;
		});

		return $this->respondOK($result);
	}
}
