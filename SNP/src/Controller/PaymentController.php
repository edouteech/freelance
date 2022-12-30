<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Payment;
use App\Entity\User;
use App\Form\Type\PaymentCreateType;
use App\Form\Type\PaymentReadType;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Repository\UserRepository;
use App\Service\EudonetAction;
use App\Service\Mailer;
use Doctrine\ORM\ORMException;
use Ekyna\Component\Payum\Monetico\Api\Api;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Payum\Core\Exception\Http\HttpException;
use Payum\Core\Payum;
use Payum\Core\Request\GetHumanStatus;
use Payum\Core\Request\Notify;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Payment Controller
 *
 * @SWG\Tag(name="Payments")
 */
class PaymentController extends AbstractController
{
	/**
	 * Get all payment
	 *
	 * @Route("/payment", methods={"GET"})
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of documents per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 * @SWG\Parameter(name="sort", in="query", type="string", description="Order result", default="id", enum={"id"})
	 * @SWG\Parameter(name="date", in="query", type="string", description="date result", default="id", enum={"id"})
	 *
	 * @IsGranted("ROLE_ADMIN")
	 * @Security(name="Authorization")
	 *
	 * @SWG\Response(response=200, description="Return payment list")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param PaymentRepository $paymentRepository
	 * @param Request $request
	 * @return JsonResponse
	 */
	public function list(PaymentRepository $paymentRepository, Request $request)
	{
		list($limit, $offset) = $this->getPagination($request);

		$form = $this->submitForm(PaymentReadType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		if(!empty($request->query->get('date')))
		{
			$criteria['date'] = $request->query->get('date');
		}

		$payments = $paymentRepository->query($limit, $offset, $criteria);

		return $this->respondOK([
			'items'=>$paymentRepository->hydrateAll($payments),
			'count'=>count($payments),
			'limit'=>$limit,
			'offset'=>$offset
		]);
	}


    /**
     * Get payment url
     *
     * @Route("/payment", methods={"POST"})
     *
     * @SWG\Parameter( name="order", in="body", required=true, description="Order informations", @SWG\Schema( type="object",
     *     @SWG\Property(property="returnUrl", type="string"),
     *     @SWG\Property(property="errorUrl", type="string"),
     *     @SWG\Property(property="orderId", type="integer"),
     * ))
     *
     * @IsGranted("ROLE_USER")
     * @Security(name="Authorization")
     *
     * @SWG\Response(response=200, description="Return payment url")
     * @SWG\Response(response=404, description="Formation course not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param OrderRepository $orderRepository
     * @param EudonetAction $eudonetAction
     * @param PaymentRepository $paymentRepository
     * @param Payum $payum
     * @param Request $request
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     */
	public function getUrl(OrderRepository $orderRepository, EudonetAction $eudonetAction, PaymentRepository $paymentRepository, Payum $payum, Request $request)
	{
        /** @var User $user */
        $user = $this->getUser();

		if( !$email = $user->getEmail() )
			return $this->respondError('Unable to find user email');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)){

            if( $address = $user->getAddressWithEmail() ){

                $eudonetAction->pull($address);
                return $this->respondError('An error occurred, please retry');
            }

            return $this->respondError('Invalid email');
        }

		$form = $this->submitForm(PaymentCreateType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		$criteria = $form->getData();

		if( !$order = $orderRepository->findOneBy(['id'=>$criteria['orderId'], 'contact'=>$user->getContact(), 'company'=>$user->getCompany()]) )
			return $this->respondNotFound('Order not found');

		if( $order->getTotalAmount() == 0 )
			return $this->respondError('Order is free');

		$payment = new Payment();
		$payment->setNumber($order->getInvoice());
		$payment->setCurrencyCode('EUR');
		$payment->setTotalTax($order->getTotalTax()*100);
		$payment->setTotalAmount(($order->getTotalAmount()+$order->getTotalTax())*100);
		$payment->setClientId($user->getMemberId());
		$payment->setUser($user);
		$payment->setClientEmail($email);
		$payment->setReturnUrl($criteria['returnUrl']);
		$payment->setErrorUrl($criteria['errorUrl']);

		if( in_array($order->getType(), ['membership_snpi', 'membership_vhs', 'membership_caci',  'register', 'membership_asseris']) )
		{
			if( in_array($order->getType(), ['membership_caci', 'register']) )
				$payment->setEntity('asseris');
			else
				$payment->setEntity(str_replace('membership_', '', $order->getType()));
		}

		if( $user->isLegalRepresentative() ){

			/** @var Company $company */
			$company = $user->getCompany();

            try {

                $payment->setStreet($company->getStreet('|'));
                $payment->setCity($company->getCity());
                $payment->setZip($company->getZip());

            } catch (Exception $e) {

                return $this->respondBadRequest($e->getMessage());
            }

			//todo: get real country code
			$payment->setCountryCode($company->getCountry());
		}
		else{

			/** @var Contact $contact */
			$contact = $user->getContact();
			$address = $contact->getHomeAddress();

            try {

                $payment->setStreet($address->getStreet(','));
                $payment->setCity($address->getCity());
                $payment->setZip($address->getZip());

            } catch (Exception $e) {

                return $this->respondBadRequest($e->getMessage());
            }

            $eudonetAction->pull($address);

            //todo: get real country code
			$payment->setCountryCode($address->getCountry());
		}

		$paymentRepository->save($payment);

		$order->addPayment($payment);

		if( !$gateway = $order->getGateway() ){

			$gateway = $order->getType();
			$order->setGateway($gateway);
		}

		$orderRepository->save($order);

        $paymentRepository->save($payment);

        $captureToken = $payum->getTokenFactory()->createCaptureToken($gateway, $payment, 'done');

        return $this->respondOK($captureToken->getTargetUrl());
	}


	/**
	 * Payment process complete
	 *
	 * @Route("/payment/done", methods={"GET"}, name="done")
	 *
	 * @SWG\Response(response=200, description="Redirect")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param Payum $payum
	 * @return RedirectResponse
	 * @throws Exception
	 */
	public function done(Request $request, Payum $payum)
	{
		$token = $payum->getHttpRequestVerifier()->verify($request);

		$gateway = $payum->getGateway($token->getGatewayName());
		$gateway->execute($status = new GetHumanStatus($token));

		$payum->getHttpRequestVerifier()->invalidate($token);

		$payment = $status->getFirstModel();

		if( $status->isCaptured() )
			return $this->redirect($payment->getReturnUrl());

		return $this->redirect($payment->getErrorUrl());
	}


	/**
	 * Process payment notification from Monetico
	 *
	 * @Route("/monetico/notify", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="Return expected response")
	 * @SWG\Response(response=404, description="Payment reference not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param Payum $payum
	 * @param PaymentRepository $paymentRepository
	 * @param EudonetAction $eudonetAction
	 * @param LoggerInterface $logger
	 * @param Mailer $mailer
	 * @return Response
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
	public function notify(Request $request, Payum $payum, PaymentRepository $paymentRepository, EudonetAction $eudonetAction, LoggerInterface $logger, Mailer $mailer)
	{
		if (null === $reference = $request->get('reference'))
			throw new HttpException('No reference in request.');

		if (null === $comment = $request->get('texte-libre'))
			throw new HttpException('No comment in request.');

		if ( !$payment = $paymentRepository->findOneBy(['number' => $reference], ['id'=>'DESC']) )
			throw new HttpException('Payment #'.$reference.' not found.');

        $payment->setReference($request->get('reference'));
        $payment->setTpe($request->get('TPE'));

		$comment = json_decode($comment, true);

		if (!isset($comment['gateway']))
			throw new HttpException('No gateway in comment.');

		$gateway = $payum->getGateway($comment['gateway']);

		$gateway->execute(new Notify($payment));
		$gateway->execute($status = new GetHumanStatus($payment));

        $payment->setStatus($status->getValue());
        $paymentRepository->save($payment);

		if( $status->isCaptured() ){

			$body = $mailer->createBodyMail('order/validated.html.twig', ['title'=>'Paiement réceptionné', 'returnUrl'=>$payment->getReturnUrl(), 'reference'=>$reference]);
			$mailer->sendMessage($payment->getClientEmail(), 'Paiement réceptionné', $body);
		}
		elseif( !$status->isCanceled() && !$status->isNew() && !$status->isFailed() ){

			$logger->error('Payment #' . $payment->getId().' status: ' . $status->getValue().', request:'.json_encode($request->request->all()));
		}

		return new Response(Api::NOTIFY_SUCCESS);
	}


	/**
	 * Manually validate payment based on number
	 *
	 * @Route("/monetico/validate/{reference}/{tpe}", methods={"GET"}, name="validate_payment")
	 *
	 * @Security(name="Authorization")
	 *
	 * @SWG\Response(response=200, description="Return expected response")
	 * @SWG\Response(response=404, description="Payment reference not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param PaymentRepository $paymentRepository
	 * @param EudonetAction $eudonetAction
	 * @param UserRepository $userRepository
	 * @param Mailer $mailer
	 * @param $reference
	 * @param $tpe
	 * @return Response
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function validate(Request $request, PaymentRepository $paymentRepository, EudonetAction $eudonetAction, UserRepository $userRepository, Mailer $mailer, $reference, $tpe)
	{
		if (!$payment = $paymentRepository->findOneBy(['number' => $reference], ['id'=>'DESC']))
			return $this->respondNotFound('Payment #'.$reference.' not found.');

		if (!$payment->getOrder() )
			return $this->respondNotFound('Order not found for payment #'.$reference.'.');

		if (!$user = $payment->getUser() )
			return $this->respondNotFound('User not found');

        $payment->setReference($request->get('reference'));
        $payment->setTpe($request->get('TPE'));
		$payment->setStatus(GetHumanStatus::STATUS_CAPTURED);

		$paymentRepository->save($payment);

		$body = $mailer->createBodyMail('order/validated.html.twig', ['title'=>'Paiement réceptionné', 'returnUrl'=>$payment->getReturnUrl(), 'reference'=>$reference]);
		$mailer->sendMessage($payment->getClientEmail(), 'Paiement réceptionné', $body);

		return $this->respondOk([
			'user'=>$userRepository->hydrate($user),
			'reference'=>$reference,
			'returnUrl'=>$payment->getReturnUrl()
		]);
	}
}
