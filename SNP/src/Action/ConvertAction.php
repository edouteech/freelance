<?php

namespace App\Action;

use App\Entity\Payment;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Model\PaymentInterface;
use Payum\Core\Request\Convert;
use Payum\Core\Request\GetCurrency;

class ConvertAction implements ActionInterface, GatewayAwareInterface
{
	use GatewayAwareTrait;

	private function stripAccents($str) {

		return strtr(utf8_decode($str), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
	}

	private function clean($str, $length=false) {

        $str = preg_replace('/[^A-Za-z0-9. ]/', '-', $this->stripAccents($str));

        if( $length )
            return substr($str, 0, $length);

        return $str;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Convert $request
	 */
	public function execute($request)
	{
		RequestNotSupportedException::assertSupports($this, $request);

		/** @var Payment $payment */
		$payment = $request->getSource();
		$model = ArrayObject::ensureArrayObject($payment->getDetails());

		if (false == $model['reference'])
			$model['reference'] = $payment->getNumber();

		if (false == $model['amount']) {

			$this->gateway->execute($currency = new GetCurrency($payment->getCurrencyCode()));
			$amount = (string)$payment->getTotalAmount();

			if (0 < $currency->exp) {

				$divisor = pow(10, $currency->exp);
				$amount = (string)round($amount / $divisor, $currency->exp);

				if (false !== $pos = strpos($amount, '.'))
					$amount = str_pad($amount, $pos + 1 + $currency->exp, '0', STR_PAD_RIGHT);
			}

			$model['amount'] = $amount;
			$model['currency'] = (string)strtoupper($currency->code);
		}

		if (false == $model['email'])
			$model['email'] = $payment->getClientEmail();

		$order = $payment->getOrder();

		if (false == $model['comment']) {

			$model['comment'] = json_encode([
				'version' => 1,
				'numAdherent' => $payment->getClientId(),
				'gateway' => $order->getGateway()
			]);
		}

		// The 3DSecure v2 require that you provide the order context.
		// @see https://www.monetico-paiement.fr/fr/info/documentations/Monetico_Paiement_documentation_technique_v2.1.pdf (page 73)
		$street = explode('|', $payment->getStreet());

		$model['context'] = [
			'billing' => [
				'addressLine1' => $this->clean($street[0], 50),
				'city'         => $this->clean($payment->getCity(), 50),
				'postalCode'   => $this->clean($payment->getZip(), 10),
				'country'      => $this->clean($payment->getCountryCode())
			]
		];

		$request->setResult($model);
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports($request)
	{
		return $request instanceof Convert
			&& $request->getSource() instanceof PaymentInterface
			&& $request->getTo() == 'array';
	}
}