<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AbstractCommand extends Command {

	/** @var ContainerInterface $container */
	protected $container;

	/** @var OutputInterface $output */
	protected $output=false;

	/** @var EntityManagerInterface $entityManager */
	protected $entityManager;

    protected $logger;

	protected $maintenanceOptions = ['SIGNATURES_ENABLED', 'TRAINING_ELEARNING_ENABLED', 'TRAINING_INSTRUCTOR_LED_ENABLED', 'TRAINING_IN_HOUSE_ENABLED', 'TRAINING_WEBINAR_ENABLED', 'CACI_REGISTRATION', 'CACI_INSURANCES', 'DOCUMENTS_ENABLED'];
	protected $renewalOptions = ['MEMBERSHIP_RENEWAL_CACI', 'MEMBERSHIP_RENEWAL_COMPANY'];


	/**
	 * AbstractCommand constructor.
	 * @param ContainerInterface $container
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(ContainerInterface $container, ?LoggerInterface $logger=NULL)
	{
		parent::__construct();

		$this->container = $container;
		$this->logger = $logger;

		$project_dir= $this->container->get('kernel')->getProjectDir();

		$command = $_SERVER['argv'][1]??'';
        $sync_eudonet = ($command == 'app:sync:eudonet' && ($_SERVER['argv'][2]??false));

		if( file_exists($project_dir.'/.maintenance') && $command != 'doctrine:migrations:migrate' && $command != 'app:import' && !$sync_eudonet )
		    $this->error("Website is in maintenance\n");

		if( file_exists($project_dir.'/.lock') && $command != 'doctrine:migrations:migrate' ){

			$locktime = intval(file_get_contents($project_dir.'/.lock'));

			if( $locktime > strtotime('now') )
				$this->error("Website is locked\n");
			else
				unlink($project_dir.'/.lock');
		}

		$this->entityManager = $container->get('doctrine')->getManager();
	}

	/**
	 * @param $directory_parameter
	 * @return string
	 */
	public function getPath($directory_parameter){

		return $this->container->get('kernel')->getProjectDir().$this->container->getParameter($directory_parameter);
	}


	public function exists($directory_parameter, $file){

		if( !$file )
			return false;

		return file_exists($this->getPath($directory_parameter).'/'.$file);
	}

	/**
	 *
	 */
	protected function configure () {

		$this->setName('app:abstract');
		$this->setDescription("Do nothing.");
	}

	/**
	 * @param $message
	 * @param bool $exit
	 */
	protected function error($message, $exit=true){

		if( is_array($message) )
			$message = implode(', ', $message);

		if( $this->logger && $message != 'OK' && !empty($message) ){

			$this->logger->error($message);

			if( $this->output )
				$this->output->writeln("<error>".$message."</error>");
			else
				echo $message;
		}

		if( $exit )
			exit(0);
	}
}