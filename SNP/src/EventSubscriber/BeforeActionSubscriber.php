<?php

namespace App\EventSubscriber;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use function json_last_error;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class BeforeActionSubscriber implements EventSubscriberInterface
{
	private $container;

	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}


	/**
	 * @return array
	 */
	public static function getSubscribedEvents()
	{
		return [KernelEvents::CONTROLLER => 'onKernelController'];
	}


	/**
	 * @param ControllerEvent $event
	 * @return void
	 */
	public function onKernelController(ControllerEvent $event)
	{
		if (!$event->isMasterRequest())
			return;

		$project_dir= $this->container->get('kernel')->getProjectDir();
		$request = $event->getRequest();

		$client_ip = $request->getClientIp();
		$maintenance_ips = array_map('trim', explode(',', $_ENV['MAINTENANCE_IPS']??''));
		$route = $request->attributes->get('_route');

		if( $route != 'app_payment_notify' && $route != 'app_formationcourse_processevent' ){

			if( file_exists($project_dir.'/.lock') ){

				$locktime = intval(file_get_contents($project_dir.'/.lock'));

				if( $locktime > strtotime('now') ) {

					$event->setController(function () use ($project_dir) {

						return new JsonResponse([
							'status' => 'error',
							'status_code' => 509,
							'status_text' => "En raison d'une forte affluence, la connexion peut se rendre impossible. Si c'est le cas merci de réessayer plus tard"
						], 509);
					});
				}
				else{

					@unlink($project_dir.'/.lock');
				}
			}

			if( file_exists($project_dir.'/.maintenance') && !in_array($client_ip, $maintenance_ips) ){

				$event->setController(function() use($project_dir) {

					$maintenance = file_get_contents($project_dir.'/.maintenance');

					return new JsonResponse([
						'status' => 'error',
						'status_code' => 503,
						'status_text' => $maintenance?$maintenance:'Website is in maintenance'
					], 503);
				});

				return;
			}
		}

		if ($request->getContentType() != 'json' || !$request->getContent())
			return;

		$data = json_decode($request->getContent(), true);

		if (json_last_error() !== JSON_ERROR_NONE)
			$data = [];

		$request->request->replace(is_array($data) ? $data : []);
	}
}