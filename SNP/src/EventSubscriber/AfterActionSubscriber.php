<?php

namespace App\EventSubscriber;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class AfterActionSubscriber implements EventSubscriberInterface
{
	/**
	 * @return array
	 */
	public static function getSubscribedEvents()
	{
		return array(
			KernelEvents::RESPONSE => 'onKernelResponse',
		);
	}


	/**
	 * @param ResponseEvent $event
	 */
	public function onKernelResponse(ResponseEvent $event)
	{
		$response = $event->getResponse();

		$response->headers->set('X-Robots-Tag', 'noindex');

		if( $response instanceof JsonResponse ){

			$cms_url = str_replace('"','', json_encode($_ENV['CMS_URL'].'/uploads'));
			$api_url = str_replace('"','', $event->getRequest()->getSchemeAndHttpHost().'/cms/uploads');

			$content = str_replace($cms_url, $api_url, $response->getContent());

			$response->setContent($content);
		}
	}
}