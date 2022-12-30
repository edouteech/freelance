<?php

namespace App\Command;

use App\Entity\Option;
use App\Repository\OptionRepository;
use App\Service\CurlClient;
use App\Service\EudonetConnector;
use Exception;
use Psr\Log\LoggerInterface;
use sixlive\DotenvEditor\DotenvEditor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HeartbeatCommand extends AbstractCommand {

	/** @var OptionRepository $optionRepository */
	protected $optionRepository;

	private $eudonet;
	private $client;
	private $dotenvEditor;


	public function __construct (ContainerInterface $container, EudonetConnector $eudonet, CurlClient $curlClient, LoggerInterface $logger) {

		parent::__construct($container, $logger);

		$this->eudonet = $eudonet;
		$this->client = $curlClient;

		$projectDir = $this->container->get('kernel')->getProjectDir();

		$this->dotenvEditor = new DotenvEditor();
		$this->dotenvEditor->load($projectDir.'/.env.local');
	}


	/**
	 * Configure
	 */
	protected function configure () {

		$this->setName('app:heartbeat');
		$this->setDescription("Update service status");
	}


	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return bool
	 */
	public function execute (InputInterface $input, OutputInterface $output) {

		$this->optionRepository = $this->entityManager->getRepository(Option::class);

		$this->getEudonetStatus();
		$this->getCMSStatus();

		return 1;
	}


	public function getEudonetStatus() {

		try {

			$this->eudonet->getMetaInfos(300, [301]);
			$this->optionRepository->set('eudonet_status', false);
		}
		catch (Exception $e){

			foreach ($this->maintenanceOptions as $option){

				if( $option != 'DOCUMENTS_ENABLED')
					$this->dotenvEditor->set($option, 0);
			}

			$this->dotenvEditor->save();

			$this->optionRepository->set('eudonet_status', $e->getMessage());
			$this->error($e->getMessage(), false);
		}
	}


	public function getCMSStatus() {

		try {

			$this->client->get($_ENV['CMS_URL']);
			$this->optionRepository->set('cms_status', false);
		}
		catch (Exception $e){

			$this->dotenvEditor->set('DOCUMENTS_ENABLED', 0);
			$this->dotenvEditor->save();

			$this->optionRepository->set('cms_status', $e->getMessage());
			$this->error($e->getMessage(), false);
		}
	}
}