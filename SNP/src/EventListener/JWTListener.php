<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;

class JWTListener extends AbstractListener
{
    /**
     * @param JWTExpiredEvent $event
     */
    public function onJWTExpired(JWTExpiredEvent $event)
    {
        $event->setResponse(new JsonResponse([
            "status" => 'Token Expired',
            "status_code" => Response::HTTP_UNAUTHORIZED, // 401
            "status_text" => $this->translator->trans("Your token is expired, please renew it.")
        ], Response::HTTP_UNAUTHORIZED));
    }

	public function onJWTInvalid(JWTInvalidEvent $event)
	{
		$event->setResponse(new JsonResponse([
			"status" => 'Invalid Token',
			"status_code" => Response::HTTP_UNAUTHORIZED,
			"status_text" => $this->translator->trans('Your token is invalid, please login again to get a new one')
		], Response::HTTP_UNAUTHORIZED));
	}

	public function onJWTNotFound(JWTNotFoundEvent $event)
	{
		$event->setResponse(new JsonResponse([
			"status" => 'Connection required',
			"status_code" => Response::HTTP_UNAUTHORIZED, // 401
			"status_text" => $this->translator->trans('Connection required')
		], Response::HTTP_UNAUTHORIZED));
	}

	public function onJWTCreated(JWTCreatedEvent $event)
	{
		$payload = $event->getData();

		$payload['ip'] = $this->requestStack->getCurrentRequest()->getClientIp();

		unset($payload['roles']);

		$event->setData($payload);
	}
}