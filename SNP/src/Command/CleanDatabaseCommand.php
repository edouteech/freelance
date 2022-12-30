<?php

namespace App\Command;

use App\Entity\Address;
use App\Entity\Company;
use App\Entity\CompanyRepresentative;
use App\Entity\Contact;
use App\Entity\Formation;
use App\Entity\FormationCourse;
use App\Entity\FormationParticipant;
use App\Entity\Mail;

use App\Entity\Sync;

use App\Repository\AbstractRepository;
use App\Service\FtpService;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;

class CleanDatabaseCommand extends AbstractCommand {

	private $ftpService;
	private $mode;

	/**
	 * CleanEudonetCommand constructor.
	 * @param ContainerInterface $container
	 * @param LoggerInterface $logger
	 * @throws Exception
	 */
	public function __construct (ContainerInterface $container, LoggerInterface $logger) {

		parent::__construct($container, $logger);

		$this->ftpService = new FtpService(['host'=>$_ENV['EUDONET_FTP_HOST'],'login'=>$_ENV['EUDONET_FTP_LOGIN'],'password'=>$_ENV['EUDONET_FTP_PASSWORD'],'port'=>$_ENV['EUDONET_FTP_PORT']]);
	}

	/**
	 * Configure
	 */
	protected function configure () {

		$this->setName('app:clean:database');
		$this->setDescription("Clean removed companies, contacts, addresses from Eudonet");

		$this->addArgument('mode', InputArgument::OPTIONAL, 'Clean mode : dry or force');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return bool
	 * @throws Exception
	 */
	public function execute (InputInterface $input, OutputInterface $output) {

        $this->output = $output;
		$this->mode = $input->getArgument('mode');

		$syncRepository = $this->entityManager->getRepository(Sync::class);
		$clean = $syncRepository->start('database-clean');

		try {

			$tables = [
				'200'=>['className'=>Contact::class, 'fields'=>['status'=>['removed','refused','not_member']], 'update'=>['status'=>'removed']],
				'300'=>['className'=>Company::class, 'fields'=>['status'=>['removed','not_member']], 'update'=>['status'=>'removed']],
				'400'=>['className'=>Address::class, 'fields'=>['isActive'=>0, 'isArchived'=>1]],
				'1400'=>['className'=>CompanyRepresentative::class, 'fields'=>['archived'=>1]],
				//'1600'=>['className'=>CompanyBusinessCard::class, 'fields'=>['isActive'=>0]],
				'11300'=>['className'=>FormationCourse::class, 'fields'=>['status'=>['canceled']], 'update'=>['status'=>'canceled']],
				'11400'=>['className'=>Formation::class, 'fields'=>['isActive'=>0]],
				'11500'=>['className'=>FormationParticipant::class, 'fields'=>['registered'=>0], 'update'=>['registered'=>0, 'present'=>0]],
				'11900'=>['className'=>Mail::class, 'fields'=>[]]
			];

			foreach ($tables as $table_id=>$properties)
				$this->process($properties['className'], $table_id, $properties['fields'], $properties['update']??false);
		}
		catch (Throwable $t){

			$this->error($t->getMessage(), false);
		}

		$syncRepository->end($clean);

		$this->output->writeln("<info>Clean completed</info>");

		return 1;
	}


	/**
	 * @param $className
	 * @param $table_id
	 * @param $fields
	 * @return void
	 * @throws Exception
	 */
	private function process($className, $table_id, $fields, $update) {

		$yesterday = new DateTime();
		$yesterday->modify('-1 day');
		$yesterday->setTime(0,0,0);

		$date = $yesterday->format('dmY');

		if( $csv_file = $this->ftpService->get('/ALASKA/'.$table_id.'_03032022.csv') ){

			$this->output->writeln('<question>Cleaning '.$className.'</question>');

			$eudonet_ids = file($csv_file);

			if( is_string($eudonet_ids[0]) )
				$eudonet_ids[0] = preg_replace("/[^0-9]/", "", $eudonet_ids[0]);

			$eudonet_ids = array_map('intval', array_map('trim', $eudonet_ids));

			/** @var AbstractRepository $repository */
			$repository = $this->entityManager->getRepository($className);
			$db_ids = $repository->findIdsBy($fields, $yesterday);

			$removed_ids = array_values(array_diff($db_ids, $eudonet_ids));

			if( empty($this->mode) || $this->mode === 'dry'){

				$this->output->writeln('<info>'.count($removed_ids).' to '.(empty($fields)?'remove':'archive').'</info>');

				if( count($removed_ids) )
					$this->output->writeln('<info>Sample ids '.json_encode(array_splice($removed_ids, 0, 10)).'</info>');
			}
			elseif( $this->mode === 'force' ){

				if( empty($fields) ){

                    $repository->bulkDelete($removed_ids);
                    $this->output->writeln('<info>'.count($removed_ids).' removed</info>');
                }
				else{

                    $update = $update?:$fields;
                    $repository->bulkUpdate($removed_ids, $update);

                    $this->output->writeln('<info>'.count($removed_ids).' archived</info>');
                }
			}
		}
	}
}
