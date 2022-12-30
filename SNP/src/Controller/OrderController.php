<?php

namespace App\Controller;

use App\Entity\Registration;
use App\Repository\UserRepository;
use App\Service\CaciService;
use DateTime;
use Exception;
use Payum\Core\Request\GetHumanStatus;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;
use App\Entity\Order;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Payment;
use App\Service\Mailer;
use App\Entity\OrderDetail;
use App\Service\EudonetAction;
use App\Service\SnpiConnector;
use App\Service\ZoomConnector;
use Doctrine\ORM\ORMException;
use App\Service\ServicesAction;
use Swagger\Annotations as SWG;
use App\Service\EudonetConnector;
use App\Form\Type\OrderCreateType;
use App\Repository\OrderRepository;
use App\Service\ElearningConnector;
use App\Repository\ContactRepository;
use Psr\Cache\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\OrderDetailRepository;
use Doctrine\ORM\NonUniqueResultException;
use Nelmio\ApiDocBundle\Annotation\Security;
use App\Repository\FormationCourseRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\FormationParticipantRepository;
use App\Repository\RegistrationRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * Order Controller
 *
 * @SWG\Tag(name="Orders")
 *
 * @IsGranted("ROLE_USER")
 * @Security(name="Authorization")
 */
class OrderController extends AbstractController
{
	private $zoomConnector;

	public function __construct(TranslatorInterface $translator, EntityManagerInterface $entityManager, ZoomConnector $zoomConnector)
	{
		parent::__construct($translator, $entityManager);

		$this->zoomConnector = $zoomConnector;
	}

	/**
	 * @param Contact[] $contacts
	 * @throws Exception
	 */
	private function checkEmails($contacts, $company, $formationCourse){

		$emails = [];
		$registrants = $this->zoomConnector->getWebinarRegistrants($formationCourse->getWebinarId());

		foreach ($contacts as $contact){

			if( !$email = $contact->getEmail($company) )
				throw new Exception('Email is required for ['.$contact->__toString().']');

			if( in_array($email, $emails) )
				throw new Exception('Two or more contacts have the same email ['.$email.']');

			if( isset($registrants[$email]) )
				throw new Exception('Email ['.$email.'] already registered on Zoom');

			$emails[] = $contact->getEmail($company);
		}

		return $emails;
	}


    /**
     * Create order
     *
     * @Route("/order", methods={"POST"})
     *
     * @SWG\Parameter( name="user", in="body", required=true, description="Order information", @SWG\Schema( type="object",
     *     @SWG\Property(property="type", type="string", enum={"formation"}),
     *     @SWG\Property(property="productId", type="integer"),
     *     @SWG\Property(property="contacts", type="array", @SWG\Items(type="integer"))
     * ))
     *
     * @SWG\Response(response=200, description="Returns an order detail")
     * @SWG\Response(response=404, description="Formation course not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     *
     * @param RegistrationRepository $registrationRepository
     * @param FormationCourseRepository $formationCourseRepository
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param ServicesAction $servicesAction
     * @param OrderRepository $orderRepository
     * @param SnpiConnector $snpiConnector
     * @param EudonetAction $eudonet
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws Exception|InvalidArgumentException
     */
	public function create(Request $request, RegistrationRepository $registrationRepository, FormationCourseRepository $formationCourseRepository, FormationParticipantRepository $formationParticipantRepository, ServicesAction $servicesAction, OrderRepository $orderRepository, SnpiConnector $snpiConnector, EudonetAction $eudonet)
	{
		$user = $this->getUser();

		$form = $this->submitForm(OrderCreateType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		//todo: check if contact is really a collaborator of the user company
		//todo: split code

		$order = new Order();

		$order->setType($criteria['type']);
		$order->setUser($user);
		$order->setIp($orderRepository->getHash($request->getClientIp()));
		$order->setGateway($criteria['type']);

		switch ($criteria['type']) {

			case 'formation':

				//todo: check if formation is in future

				if( !$formationCourse = $formationCourseRepository->findOneBy(['id'=>$criteria['productId'], 'status'=>'confirmed']) )
					return $this->respondNotFound('Formation course not found');

				$contacts = $formationCourseRepository->filterParticipants($formationCourse, $criteria['contacts']);
                $formation = $formationCourse->getFormation();

				if( !$quantity = count($contacts) )
					return $this->respondError('No contact added');

                if( $previousFormation = $formation->getPreviousFormation() ){

                    foreach ($contacts as $contact){

                        if( !$formationParticipantRepository->findOneByFormation($previousFormation, $contact) )
                            return $this->respondNotFound('%s has not completed "%s"', [$contact, $previousFormation]);
                    }
                }

				$eudonet->pull($formationCourse);

				if( $formationCourse->getRemainingPlaces() < $quantity && $formationCourse->getFormat() != 'e-learning' )
					throw new Exception('Not enough seat available for this formation course');

				$order->setMessage('Formation');

				$orderDetail = new OrderDetail();
				$orderDetail->setTitle($formation->getTitle());

				$description = [
					'duration'=>$formationCourse->getHours(),
					'format'=>$formationCourse->getFormat()
				];

				if( in_array($formationCourse->getFormat(), ['e-learning', 'webinar']) )
					$order->setGateway($criteria['type'].'_elearning');
				elseif( $company = $formationCourse->getCompany() )
					$description['city'] = $company->getCity();

				if( $formationCourse->getFormat() != 'e-learning' )
					$description['startAt'] = $formationCourse->getStartAt()->getTimestamp()*1000;

				if( $formationCourse->getFormat() === 'webinar' ){

					if( !$formationCourse->getWebinarId() ){

                        try {

                            $servicesAction->createWebinar($formationCourse);

                        } catch (Throwable $t) {

                            throw new Exception($t->getMessage());
                        }
                    }
                    else
                        $this->checkEmails($contacts, $user->getCompany(), $formationCourse);
                }

				$orderDetail->setDescription($description);
				$orderDetail->setPrice($formation->getPrice());
				$orderDetail->setTaxRate($formationCourse->getTaxRate());
				$orderDetail->setQuantityInStock($formationCourse->getRemainingPlaces());
				$orderDetail->setQuantity($quantity);
				$orderDetail->setProductId($criteria['productId']);
				$orderDetail->addContacts($contacts);

				$order->addDetail($orderDetail);

				break;

			case 'signature':

				$packs = $snpiConnector->getPacks();
				$selectedPack = false;

				foreach ($packs as $pack){

					if( $pack['id'] == $criteria['productId'])
						$selectedPack = $pack;
				}

				if( !$selectedPack )
					throw new Exception('Selected pack is not available');

				$order->setMessage('Signatures');

				$orderDetail = new OrderDetail();
				$orderDetail->setTitle('Pack de '.$selectedPack['count'].' signatures');
				$orderDetail->setTaxRate($selectedPack['taxes']/100);
				$orderDetail->setPrice($selectedPack['total_price_ht']);
				$orderDetail->setQuantity(1);
				$orderDetail->setProductId($criteria['productId']);
				$order->addDetail($orderDetail);

				break;

			case 'membership_asseris':

				$status = $eudonet->getMembershipStatus($user, 'asseris');
				$order->setMessage('Cotisation Asseris');

				foreach ($status['details'] as $detail){

					$orderDetail = new OrderDetail();
					$orderDetail->setTitle($detail['category']);
					$orderDetail->setTaxRate(0);
					$orderDetail->setPrice($detail['amount']);
					$orderDetail->setProductId($detail['id']);
					$orderDetail->setQuantity(1);

					$order->addDetail($orderDetail);
				}

				break;

			case 'membership_caci':
			case 'register':

				$status = $eudonet->getMembershipStatus($user, 'caci');

				$order->setMessage('Cotisation CACI');

				if( $user->isRegistering() && $registration = $user->getRegistration() ){

					$registration->setPayment(true);
					$registrationRepository->save($registration);
				}

				foreach ($status['details'] as $detail){

					$orderDetail = new OrderDetail();
					$orderDetail->setTitle($detail['category']);
					$orderDetail->setTaxRate(0);
					$orderDetail->setPrice($detail['amount']);
					$orderDetail->setProductId($detail['id']);
					$orderDetail->setQuantity(1);

					$order->addDetail($orderDetail);
				}

				break;

			case 'membership_snpi':

				$status = $eudonet->getMembershipStatus($user, 'snpi');
				$order->setMessage('Cotisation SNPI');

				foreach ($status['details'] as $detail){

					$orderDetail = new OrderDetail();
					$orderDetail->setTitle("Cotisations pour l'année ".$detail['year']);
					$orderDetail->setTaxRate(0.2);
					$orderDetail->setPrice($detail['amount']/1.2);
					$orderDetail->setProductId($detail['id']);
					$orderDetail->setQuantity(1);

					$order->addDetail($orderDetail);
				}

				break;

			case 'membership_vhs':

				$status = $eudonet->getMembershipStatus($user, 'vhs');

				$order->setMessage('Primes VHS');

				foreach ($status['details'] as $detail){

					$orderDetail = new OrderDetail();
					$orderDetail->setTitle($detail['category']);
					$orderDetail->setTaxRate(0);
					$orderDetail->setPrice($detail['amount']);
					$orderDetail->setProductId($detail['id']);
					$orderDetail->setQuantity(1);

					$order->addDetail($orderDetail);
				}

				break;

			default:
				return $this->respondError('Invalid order type');
		}

		$totalAmount = 0;
		foreach ($order->getDetails() as $detail ){
			$totalAmount += $detail->getPrice();
		}

		$orderRepository->save($order);

		return $this->respondOK($orderRepository->hydrate($order, $orderRepository::$HYDRATE_FULL));
	}


    /**
     * Update order detail
     *
     * @Route("/order/{id}", methods={"POST"}, requirements={"id"="\d+"})
     *
     * @SWG\Parameter( name="user", in="body", required=true, description="Order information", @SWG\Schema( type="object",
     *     @SWG\Property(property="contacts", type="array", @SWG\Items(type="integer"))
     * ))
     *
     * @SWG\Response(response=200, description="Returns an order detail")
     * @SWG\Response(response=404, description="Formation course not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     *
     * @param int $id
     * @param FormationParticipantRepository $formationParticipantRepository
     * @param OrderDetailRepository $orderDetailRepository
     * @param OrderRepository $orderRepository
     * @param ContactRepository $contactRepository
     * @param FormationCourseRepository $formationCourseRepository
     * @param EudonetAction $eudonet
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws NonUniqueResultException
     */
	public function update(Request $request, int $id, FormationParticipantRepository $formationParticipantRepository, OrderDetailRepository $orderDetailRepository, OrderRepository $orderRepository, ContactRepository $contactRepository, FormationCourseRepository $formationCourseRepository, EudonetAction $eudonet)
	{
		$user = $this->getUser();

		if( !$order = $orderRepository->findOneBy(['id'=>$id, 'contact'=>$user->getContact(), 'company'=>$user->getCompany()]) )
			return $this->respondNotFound('Order not found');

		$contacts = $contactRepository->findBy(['id'=>$request->get('contacts')]);

		switch ($order->getType()){

			case 'formation':

				$orderDetail = $order->getDetail(0);

				if( !$formationCourse = $formationCourseRepository->findOneBy(['id'=>$orderDetail->getProductId(), 'status'=>'confirmed']) )
					return $this->respondNotFound('Formation course not found');

                $contacts = $formationCourseRepository->filterParticipants($formationCourse, $contacts);
                $formation = $formationCourse->getFormation();

                if( !$quantity = count($contacts) )
                    return $this->respondError('No contact added');

                if( $previousFormation = $formation->getPreviousFormation() ){

                    foreach ($contacts as $contact){

                        if( !$formationParticipantRepository->findOneByFormation($previousFormation, $contact) )
                            return $this->respondNotFound('%s has not completed "%s"', [$contact, $previousFormation]);
                    }
                }

				$eudonet->pull($formationCourse);

				if( $formationCourse->getRemainingPlaces() < $quantity && $formationCourse->getFormat() != 'e-learning' )
					return $this->respondError('Not enough seat available for this formation course');

				if( $formationCourse->getFormat() === 'webinar' )
					$this->checkEmails($contacts, $user->getCompany(), $formationCourse);

				$orderDetail->setQuantityInStock($formationCourse->getRemainingPlaces());
				$orderDetail->setQuantity($quantity);
				$orderDetail->addContacts($contacts);

				break;

			default:
				return $this->respondError('Invalid order type');
		}

		$order->setUpdatedAt(new DateTime());
		$orderRepository->save($order);

		return $this->respondOK($orderDetailRepository->hydrate($orderDetail));
	}


	/**
	 * Get order summary
	 *
	 * @Route("/order/{id}", methods={"GET"}, requirements={"id"="\d+"})
	 *
	 * @SWG\Response(response=200, description="Returns an order detail")
	 * @SWG\Response(response=404, description="Formation course not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param OrderRepository $orderRepository
	 * @param int $id
	 * @return JsonResponse
	 */
	public function find(OrderRepository $orderRepository, int $id)
	{
		$user = $this->getUser();

		if( !$order = $orderRepository->findOneBy(['id'=>$id, 'contact'=>$user->getContact(), 'company'=>$user->getCompany()]) )
			return $this->respondNotFound('Order not found');

		return $this->respondOK($orderRepository->hydrate($order, $orderRepository::$HYDRATE_FULL));
	}


    /**
     * Execute order after payment
     *
     * @Route("/order/{id}/execute", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @SWG\Response(response=200, description="Return an order")
     * @SWG\Response(response=404, description="Order not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param $id
     * @param UserRepository $userRepository
     * @param OrderRepository $orderRepository
     * @param RegistrationRepository $registrationRepository
     * @param EudonetAction $eudonetAction
     * @param ElearningConnector $elearningConnector
     * @param EudonetAction $eudonet
     * @param EudonetConnector $eudonetConnector
     * @param FormationCourseRepository $formationCourseRepository
     * @param SnpiConnector $snpiConnector
     * @param CaciService $caciService
     * @param OrderDetailRepository $orderDetailRepository
     * @param ServicesAction $servicesAction
     * @param Mailer $mailer
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws ORMException
     * @throws Throwable
     */
	public function execute($id, UserRepository $userRepository, OrderRepository $orderRepository, RegistrationRepository $registrationRepository, EudonetAction $eudonetAction, ElearningConnector $elearningConnector, EudonetAction $eudonet, EudonetConnector $eudonetConnector, FormationCourseRepository $formationCourseRepository, SnpiConnector $snpiConnector, CaciService $caciService, OrderDetailRepository $orderDetailRepository, ServicesAction $servicesAction, Mailer $mailer)
	{
		$user = $this->getUser();

		if( !$order = $orderRepository->find($id) )
			return $this->respondNotFound('Order not found');

		if( $order->getContact() != $user->getContact() || $order->getCompany() != $user->getCompany() )
			return $this->respondNotFound('Operation not allowed');

		$payment = $order->getTotalAmount() ? $order->getPayment() : new Payment();

		if( !$payment )
			return $this->respondNotFound('Payment not found');

		if( $order->getTotalAmount() && $payment->getStatus() != GetHumanStatus::STATUS_CAPTURED )
			return $this->respondNotFound('Payment not completed');

		$messages = [];

		try {

			if( !$order->getProcessed() ){

				set_time_limit(180);
				ignore_user_abort(true);

                /** @var Company $company */
                $company = $user->getCompany();
				$orderDetails = $order->getDetails();

				if ( $order->getType() == 'register' && $user->isRegistering() ) {

					$contact = $user->getContact();

					$eudonetConnector->update('contact', $contact->getId(), ['status'=>'member']);
					$eudonetAction->pull($contact);
				}

				foreach ($orderDetails as $orderDetail){

					if( $orderDetail->getProcessed() )
						continue;

                    $paymentId = $orderDetail->getPaymentId();

                    //get crm payment id
					if( $orderDetail->getPriceWithTaxes() && !$paymentId ){

                        $data = [
                            'reference'=>$payment->getReference(),
                            'amount'=>$orderDetail->getPriceWithTaxes(),
                            'tpe'=>$payment->getTpe()
                        ];

                        if( $order->getType() == 'membership_caci' || $order->getType() == 'register')
                            $data['contract_id'] = $orderDetail->getProductId();

                        //create sub payment
                        $paymentId = $eudonet->createPayment($user, $data);

                        $orderDetail->setPaymentId($paymentId);
                        $orderDetailRepository->save($orderDetail);
					}

					//todo: maybe implement orderDetail type
					switch( $order->getType() ) {

						case  'formation':

							if( !$formationCourse = $formationCourseRepository->findOneBy(['id'=>$orderDetail->getProductId(), 'status'=>'confirmed']) )
								return $this->respondNotFound('Formation course not found');

							$contacts = $orderDetail->getContacts();

							if( $orderDetail->getProcessedStep() < 1 ){

								$eudonet->registerFormationParticipants($user, $orderDetail, $formationCourse, $contacts);
								$eudonet->pull($formationCourse);

								$orderDetail->setProcessedStep(1);
								$orderDetailRepository->save($orderDetail);
							}

							if( $orderDetail->getProcessedStep() < 2 ){

								$formation = $formationCourse->getFormation();
								$formationCompany = $formationCourse->getCompany();
								$registrants = [];

								foreach ($contacts as $contact){

									if( $formationCourse->getFormat() == 'e-learning' && !$contact->getELearningV2() ){

                                        // old way
                                        // todo: remove
										try {

                                            $servicesAction->registerForElearning($contact, $company);
											$elearningConnector->registerUser($formationCourse, $contact);
										}
										catch (Throwable $t){

											if($t->getMessage() == 'user not found for this token'){

                                                try {

													$servicesAction->registerForElearning($contact, $company, true);
													$elearningConnector->registerUser($formationCourse, $contact);
												}
												catch (Throwable $t){

													$mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Une erreur est survenue lors de l\'inscription', 'Elearning :'.$formation->getCode().',  Participant '.$contact->getLastname().' '.$contact->getFirstname().' : '.$t->getMessage());
												}
											}
											else{

												$mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Une erreur est survenue lors de l\'inscription', 'Elearning :'.$formation->getCode().',  Participant '.$contact->getLastname().' '.$contact->getFirstname().' : '.$t->getMessage());
											}
										}
									}
									elseif( $formationCourse->getFormat() == 'webinar' ){

                                        try{
                                            if( $registrant = $servicesAction->registerForWebinar($formationCourse, $contact, $company) )
                                                $registrants[] = $registrant;
                                        }
                                        catch (Exception $e){

                                            $mailer->sendAlert($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], 'Une erreur est survenue lors de l\'inscription', 'Webinar :'.$formationCourse->getWebinarId().',  Participant '.$contact->getLastname().' '.$contact->getFirstname().' : '.$e->getMessage());
                                        }
									}
									elseif( $email = $contact->getEmail($company) ){ //todo: check format

                                        $format = $formationCourse->getFormat() == 'instructor-led' ? 'Présentiel' : 'A distance (E-learning)';

                                        $bodyMail = $mailer->createBodyMail('formation/registered.html.twig', ['title'=>"Confirmation d'inscription - ".$format, 'contact' => $contact, 'formation'=>$formation, 'formationCourse'=>$formationCourse, 'company'=>$formationCompany]);
                                        $mailer->sendMessage($email, "Confirmation d'inscription - ".$format, $bodyMail);
                                    }
								}

								if( count($registrants) ){

									$messages[] = $this->translator->trans('Contacts registered to webinar');

									$bodyMail = $mailer->createBodyMail('e-learning/webinar-summary.html.twig', ['title'=>"Confirmation d'inscriptions - Webinar live", 'formation'=>$formationCourse->getFormation(), 'formationCourse'=>$formationCourse, 'registrants'=>$registrants]);
									$mailer->sendMessage($payment->getClientEmail(), "Confirmation d'inscriptions - Webinar live", $bodyMail, $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL']);
								}

								$orderDetail->setProcessedStep(2);
								$orderDetailRepository->save($orderDetail);
							}
							break;

						case  'signature':

							$params = [
								'id_payment' => $paymentId,
								'num_adherent' => $user->getMemberId(),
								'id_company' => $company->getId(),
								'id_pack' => $orderDetail->getProductId()
							];

							$snpiConnector->validateOrder($params);
							break;

						case  'membership_snpi':

							$eudonet->registerSNPIMembership($user, $paymentId, $orderDetail->getProductId());
							break;

						case  'membership_asseris':
						case  'membership_vhs':
						case  'membership_caci':

							$eudonet->updatePayment($orderDetail->getProductId(), $paymentId);
							break;

						case  'register':

							$eudonet->generateInvoice($paymentId);
							$eudonetConnector->update('contract', $orderDetail->getProductId(), ['status'=>'valid']);

							$caciService->generateMembershipBulletin($user, $orderDetail);

							break;
					}

					$orderDetail->setProcessed(true);
					$orderDetailRepository->save($orderDetail);
				}

				if( $order->getType() == 'register' && $user->isRegistering() && $registration = $user->getRegistration() ){

					$caciService->generateMembershipCaciBulletin($user);
					$caciService->saveQuote($user);

					$registration->setValidPayment(true);
					$registrationRepository->save($registration);
				}

                $order->setProcessed(true);
                $orderRepository->save($order);

                $body = $mailer->createBodyMail('order/executed.html.twig', ['title'=>'Merci pour votre achat', 'order'=>$order, 'orderDetails'=>$orderDetails, 'payment'=>$payment]);
                $mailer->sendMessage($payment->getClientEmail(), 'Merci pour votre achat', $body);
            }
		}
		catch (Throwable $t){

			$order->setError(true);
			$orderRepository->save($order);

			$message = 'Utilisateur '.$user->getMemberId().'<br/>';
			$message .= 'Référence de paiement #'.$payment->getReference().'<br/>';
			$message .= 'Erreur : '.$t->getMessage().'<br/>';
			$message .= 'Url de commande : '.$_ENV['DASHBOARD_URL'].'/order/'.$order->getId();

			$mailer->sendAlert($_ENV['ALERT_EMAIL'], 'Une erreur est survenue pour la commande #'.$order->getId(), $message);

			throw $t;
		}

		$data = $orderRepository->hydrate($order);
		$data['messages'] = array_unique($messages);

        // offset last sync to avoid flooding lock
        if( $last_sync = $user->getLastSyncAt() ){

            $last_sync->modify('-1 minute');

            $user->setLastSyncAt($last_sync);
            $userRepository->save($user);
        }

		return $this->respondOK($data);
	}
}
