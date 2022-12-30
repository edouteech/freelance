<?php

namespace App\Command;

use App\Entity\Document;
use App\Entity\Menu;
use App\Entity\News;
use App\Entity\Option;
use App\Entity\Page;
use App\Entity\Sync;
use App\Entity\Term;
use App\Repository\OptionRepository;
use App\Repository\SyncRepository;
use App\Service\CurlClient;
use App\Service\Mailer;
use App\Service\WonderpushConnector;
use DateTime;
use Doctrine\ORM\ORMException;
use Exception;
use Psr\Log\LoggerInterface;
use sixlive\DotenvEditor\DotenvEditor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Throwable;

class SyncCMSCommand extends AbstractCommand {

	private $last_sync=false;
	private $scratch=false;

	/** @var OptionRepository $optionRepository */
	private $optionRepository;

	private $client;

	/**
	 * @param ContainerInterface $container
	 * @param LoggerInterface $logger
	 * @param CurlClient $curlClient
	 */
	public function __construct (ContainerInterface $container, LoggerInterface $logger, CurlClient $curlClient) {

		parent::__construct($container, $logger);

		$this->client = $curlClient;
		$this->client->setBaseUrl($_ENV['CMS_URL']);
	}

	/**
	 * Configure
	 */
	protected function configure () {

		$this->setName('app:sync:cms');
		$this->setDescription("Synchronize documents and news");
		$this->addArgument('scratch', InputArgument::OPTIONAL, 'Import from scratch ?');
	}


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     * @throws ORMException
     * @throws ExceptionInterface
     */
	public function execute (InputInterface $input, OutputInterface $output) {

        $this->output = $output;
		$this->scratch = $input->getArgument('scratch');

		$this->optionRepository = $this->entityManager->getRepository(Option::class);

		/** @var SyncRepository $syncRepository */
		$syncRepository = $this->entityManager->getRepository(Sync::class);
		$sync = $syncRepository->start('cms');

		$is_syncing = $this->optionRepository->get('cms_is_syncing');
		$this->last_sync = $this->optionRepository->get('cms_last_sync');

		if( $is_syncing ){

			$last_sync = new DateTime($this->last_sync);
			$thirty_minutes_ago = new DateTime("now - 30 minutes");

			if( $thirty_minutes_ago->getTimestamp() < $last_sync->getTimestamp() )
				$this->error('Synchronisation is in progress');
		}

		if( $this->scratch )
			$this->last_sync = false;
		else
			$this->optionRepository->set('cms_is_syncing', true);

		$last_sync = date("Y-m-d H:i:s");

		try {

			$this->syncTerms();
			$this->syncNews();
			$this->syncDocuments();
			$this->syncEdito();
			$this->syncOptions();
			$this->syncMenus();

			$this->optionRepository->set('cms_last_sync', $last_sync);

			$maintenance = $this->optionRepository->get('maintenance');
			$this->optionRepository->setPublic('maintenance', false);

			$this->handleMaintenance($maintenance);
			$this->handleRenewal();

		} catch (Throwable $t) {

			$this->error($t->getMessage(), false);
		}

		$this->optionRepository->set('cms_is_syncing', false);
		$syncRepository->end($sync);

		$this->output->writeln("<info>Sync completed</info>");

		return 1;
	}

	/**
	 * @param $maintenance
	 */
	private function handleMaintenance($maintenance) {

		$projectDir = $this->container->get('kernel')->getProjectDir();

		$eudonet_status = $this->optionRepository->get('eudonet_status');
		$cms_status = $this->optionRepository->get('cms_status');

		if( $maintenance['activate'] !== 'all' && !$eudonet_status ){

			$dotenvEditor = new DotenvEditor();
			$dotenvEditor->load($projectDir.'/.env.local');
			$filehash = md5(json_encode($dotenvEditor->getEnv()));

			// reset options
			foreach ($this->maintenanceOptions as $option){

				if( $option != 'DOCUMENTS_ENABLED' || !$cms_status)
					$dotenvEditor->set($option, 1);
			}

			if( $maintenance['activate'] == 'partial' ){

				foreach ($maintenance['components'] as $component){
					$dotenvEditor->set($component, 0);
				}
			}

			if( $filehash != md5(json_encode($dotenvEditor->getEnv())) )
				$dotenvEditor->save();
		}
	}

	/**
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	private function handleRenewal() {

		$projectDir = $this->container->get('kernel')->getProjectDir();

		$dotenvEditor = new DotenvEditor();
		$dotenvEditor->load($projectDir.'/.env.local');

		$filehash = md5(json_encode($dotenvEditor->getEnv()));

		// reset options
		foreach ($this->renewalOptions as $option){

			$value = $this->optionRepository->get($option);
			$this->optionRepository->setPublic($option, false);

			$dotenvEditor->set($option, $value);
		}

		if( $filehash != md5(json_encode($dotenvEditor->getEnv())) )
			$dotenvEditor->save();
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	private function syncNews() {

		$path = '/news';
		$params = ['modified'=>$this->last_sync];

		$this->process($path, $params, News::class);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	private function syncOptions() {

		$path = '/options';
		$params = [];

		$this->process($path, $params, Option::class);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	private function syncMenus() {

		$path = '/menus';
		$params = [];

		$this->process($path, $params, Menu::class, true);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	private function syncTerms() {

		$path = '/terms';
		$params = [];

		$this->process($path, $params, Term::class);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	private function syncEdito() {

		$path = '/edito';
		$params = ['modified'=>$this->last_sync];

		$this->process($path, $params, Page::class);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	private function syncDocuments() {

		$path = '/documents';
		$params = ['modified'=>$this->last_sync];

		$this->processDocuments($path, $params);
	}


	/**
	 * @param $path
	 * @param $params
	 * @param $className
	 * @param bool $truncate
	 * @return void
	 * @throws Exception
	 */
	private function process ($path, $params, $className, $truncate=false) {

		$this->output->writeln('<question>Starting '.$className.' synchronisation</question>');

		$this->output->writeln('Getting data from CMS');

		$items = $this->client->get($path, $params);

		if( $items && count($items) ){

			$this->output->writeln('Inserting to database');
			$entityRepository = $this->entityManager->getRepository($className);

			if( $truncate )
				$entityRepository->truncate();

			$entityRepository->bulkInserts($items, 0);
			$this->output->writeln("<info>".count($items)." item updated</info>");
		}
		else{

			$this->output->writeln("<info>Zero updated</info>");
		}
	}

	/**
	 * @param $path
	 * @param $params
	 * @param bool $truncate
	 * @return void
	 * @throws Exception
	 */
	private function processDocuments($path, $params, $truncate = false)
	{
		$this->output->writeln('<question>Starting '. Document::class .' synchronisation</question>');

		$this->output->writeln('Getting data from CMS');

		$items = $this->client->get($path, $params);

		if( $items && count($items) ){

			$this->output->writeln('Inserting to database');
			$entityRepository = $this->entityManager->getRepository(Document::class);

			if( $truncate )
				$entityRepository->truncate();

			foreach ($items as $key => $item) {

				$items[$key]['assets'] = array_map(
					function ($asset) {

						if( !empty($asset['url']) )
							return $asset;

						return null;
					},
					$item['assets']
				);

			}

			$entityRepository->bulkInserts($items, 0);
			$this->output->writeln("<info>".count($items)." item updated</info>");
		}
		else{

			$this->output->writeln("<info>Zero updated</info>");
		}
	}
}