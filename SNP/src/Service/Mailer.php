<?php

namespace App\Service;

use App\Repository\AlertRepository;
use Doctrine\Common\Annotations\AnnotationReader;
use Exception;
use Psr\Log\LoggerInterface;
use Swift_Attachment;
use Swift_Mailer;
use Swift_Message;
use Swift_Plugins_LoggerPlugin;
use Swift_Plugins_Loggers_ArrayLogger;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Twig\Environment;

/**
 * Class Mailer
 */
class Mailer extends AbstractService
{
	private $engine;
	private $mailer;
	private $logger;
	private $alertRepository;

    /**
     * Mailer constructor.
     * @param Swift_Mailer $mailer
     * @param Environment $engine
     * @param LoggerInterface $mailLogger
     */
	public function __construct(Swift_Mailer $mailer, Environment $engine, LoggerInterface $mailLogger, AlertRepository $alertRepository)
	{
		$this->engine = $engine;
		$this->mailer = $mailer;
		$this->logger = $mailLogger;
        $this->alertRepository = $alertRepository;

		$logger = new Swift_Plugins_Loggers_ArrayLogger();
		$this->mailer->registerPlugin(new Swift_Plugins_LoggerPlugin($logger));
	}

    /**
     * @param $to
     * @param $subject
     * @param $body
     * @param bool $reply_to
     * @param array $attachments
     */
	public function sendMessage($to, $subject, $body, $reply_to=false, $attachments=[])
	{
		if( !$reply_to )
			$reply_to = $_ENV['MAILER_REPLY_TO'];

		$subject = $_ENV['MAILER_SUBJECT'].$subject;

		$mail = (new Swift_Message($subject))
			->setFrom($_ENV['MAILER_FROM'])
			->setSubject($subject)
			->setBody($body)
			->setReplyTo($reply_to)
			->setContentType('text/html');

		if( is_array($to) ){

            $mail->setBcc($to);
        }
		else{

            $mail->setTo($to);

            if( $reply_to == $_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'] )
                $mail->setBcc($_ENV['FOLLOWUP_EMAIL']);
        }

		foreach ($attachments as $attachment){

		    try{

                $mail->attach(Swift_Attachment::fromPath($attachment));
            }
            catch (Exception $e){

                $this->logger->error($e->getMessage());
            }
        }

		$this->mailer->send($mail);

		if( !is_array($to) )
			$this->logger->error('['.$to.'] '.$subject);
		else
			$this->logger->error('['.implode(',', $to).'] '.$subject);
	}

	/**
	 * @param $to
	 * @param $subject
	 * @param $message
	 * @throws Exception
	 */
	public function sendAlert($to, $subject, $message='')
	{
        if( $this->alertRepository->sentToday($to, $subject, $message) )
            return;

        $subject = $_ENV['MAILER_SUBJECT'].$subject;

        if( empty($message) )
            $message = $subject;

		$body = $this->createBodyMail('misc/alert.html.twig', ['message'=>$message]);

		$mail = (new Swift_Message($subject))
			->setFrom($_ENV['MAILER_FROM'])
			->setSubject($subject)
			->setBody($body)
			->setContentType('text/html')
			->setTo($to);

		$this->mailer->send($mail);
	}

	/**
	 * @param $view
	 * @param $parameters
	 * @return string
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	public function createBodyMail($view, $parameters)
	{
		try {
			if( is_object($parameters) )
				$parameters = $this->serialize($parameters);

			$parameters['env'] = $_ENV;
			$parameters['host'] = $_ENV['SECURE_URL'];

			if( !isset($parameters['title']) )
				$parameters['title'] = 'Communication';

			return $this->engine->render($view, $parameters);
		}
		catch (Exception $e){

			throw new Exception($e->getMessage());
		}
	}

	/**
	 * @param $object
	 * @return array
	 * @throws Exception
	 */
	public function serialize($object)
	{
		$nameConverter = new CamelCaseToSnakeCaseNameConverter();
		$classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
		$objectNormalizer = new ObjectNormalizer($classMetadataFactory, $nameConverter);
		$dateTimeNormalizer = new DateTimeNormalizer(['datetime_format' =>'Y/m/d H:i:s']);
		$serializer = new Serializer([$dateTimeNormalizer, $objectNormalizer]);

		try {
			return $serializer->normalize($object, null);
		} catch (ExceptionInterface $e) {
			throw new Exception($e->getMessage());
		}
	}
}
