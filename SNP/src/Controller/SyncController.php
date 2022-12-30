<?php

namespace App\Controller;

use Exception;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;


/**
 * Sync Controller
 *
 * @SWG\Tag(name="Syncronisation")
 *
 */
class SyncController extends AbstractController
{
	/**
	 * Sync Eudonet
	 *
	 * @Route("/sync/eudonet", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Sync completed")
	 *
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param KernelInterface $kernel
	 * @return Response
	 * @throws Exception
	 */
	public function syncEudonet(Request $request, KernelInterface $kernel)
	{
		return $this->execute($request, $kernel, 'app:sync:eudonet');
    }

	/**
	 * Sync CMS
	 *
	 * @Route("/sync/cms", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Sync completed")
	 *
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param KernelInterface $kernel
	 * @return Response
	 * @throws Exception
	 */
	public function syncCMS(Request $request, KernelInterface $kernel)
	{
		return $this->execute($request, $kernel, 'app:sync:cms');
    }

	/**
	 * @param Request $request
	 * @param KernelInterface $kernel
	 * @param $command
	 * @return Response
	 * @throws Exception
	 */
	private function execute(Request $request, KernelInterface $kernel, $command)
	{
		$client_ip = $request->getClientIp();
		$whitelisted_ips = array_map('trim', explode(',', $_ENV['WHITELIST_ADMIN_IPS']??''));

		if( !in_array($client_ip, $whitelisted_ips) )
			return new Response('IP '.$client_ip.' not authorised', 401);

		$application = new Application($kernel);
		$application->setAutoExit(false);

		$input = new ArrayInput(['command' => $command]);

		$output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL,true);
		$application->run($input, $output);

		$content = $output->fetch();
		$converter = new AnsiToHtmlConverter();

		$html = '<html><head><style type="text/css">span{display: block} body{ background: black; font-family: monospace; }</style></head><body>'.$converter->convert($content).'</body></html>';

		return new Response($html);
    }
}