<?php

namespace App\EventListener;

use App\Factory\NormalizerFactory;
use App\Response\ApiResponse;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class ExceptionListener
{
	/**
	 * @var NormalizerFactory
	 */
	private $normalizerFactory;
	private $logger;
	private $kernel;
	private $translator;

	/**
	 * ExceptionListener constructor.
	 *
	 * @param NormalizerFactory $normalizerFactory
	 * @param LoggerInterface $logger
	 * @param KernelInterface $kernel
	 * @param TranslatorInterface $translator
	 */
	public function __construct(NormalizerFactory $normalizerFactory, LoggerInterface $logger, KernelInterface $kernel, TranslatorInterface $translator)
	{
		$this->normalizerFactory = $normalizerFactory;
		$this->logger = $logger;
		$this->kernel = $kernel;
		$this->translator = $translator;
	}

	/**
	 * @param ExceptionEvent $event
	 */
	public function onKernelException(ExceptionEvent $event)
	{
		$exception = $event->getThrowable();

		$code = $exception->getCode();

		if( $this->kernel->getEnvironment() == 'prod' && ($code >= 500 || $code < 200) )
			$response = $this->createApiResponse(new Exception('Internal server error', $code));
		else
			$response = $this->createApiResponse($exception);

		$event->setResponse($response);

		$excludedCodes = [509, 404, 403];

		if( !$exception instanceof NotFoundHttpException && !in_array($exception->getCode(), $excludedCodes) && !$exception instanceof MethodNotAllowedHttpException)
			$this->log($exception);
	}

	/**
	 * Creates the ApiResponse from any Exception
	 *
	 * @param Throwable $exception
	 *
	 * @return ApiResponse
	 */
	private function createApiResponse(Throwable $exception)
	{
		$normalizer = $this->normalizerFactory->getNormalizer($exception);

		try {
			$errors = $normalizer ? $normalizer->normalize($exception) : [];
		} catch (Exception|ExceptionInterface $e) {
			$errors = [];
		}

		$status = 0;

		if( method_exists($exception, 'getStatusCode') )
			$status = $exception->getStatusCode();
		elseif( method_exists($exception, 'getCode') )
			$status = $exception->getCode();

		if( !$status ) $status = 500;
		if( $status < 200 ) $status = 500;
		if( $status > 527 ) $status = 500;

		$message = $exception->getMessage();

        preg_match_all('/\[([^]]+)\]/', $message, $params);
        $params = $params[1];

        $message = preg_replace('/\[([^]]+)\]/', '%s', $message);

		$translation = $this->translator->trans($message);

		if( !empty($params) )
            $translation = @vsprintf($translation, $params);

		return new ApiResponse($translation, $errors, $status);
	}

	private function log(Throwable $exception)
	{
		$log = [];

		$log[] = $exception->getMessage();
		$log[] = $exception->getFile(). ' on line '.$exception->getLine();

		foreach ($exception->getTrace() as $trace ){

            if( strpos($trace['file']??'', 'HttpKernel.php') !== false )
                break;

            $log[] = ($trace['file']??''). ' on line '. ($trace['line']??'');
        }

		$this->logger->error(implode(', ', $log));
	}
}