<?php

namespace App\Command;

use App\Entity\Appendix;
use App\Entity\Contact;

use App\Entity\User;
use App\Repository\AppendixRepository;
use App\Repository\ContactRepository;

use App\Repository\UserRepository;
use App\Service\EudonetAction;
use App\Service\EudonetConnector;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SyncAppendixCommand extends AbstractCommand {

	/** @var EudonetConnector $eudonet */
	private $eudonet;


	public function __construct (ContainerInterface $container, EudonetAction $eudonet, LoggerInterface $logger) {

		parent::__construct($container, $logger);

		$this->eudonet = $eudonet;
	}

	/**
	 * Configure
	 */
	protected function configure () {

		$this->setName('app:sync:appendix');
		$this->setDescription("Re-synchronize appendix from Eudonet");
		$this->addArgument('member', InputArgument::OPTIONAL, 'Member id');
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return bool
	 * @throws Exception
	 */
	public function execute (InputInterface $input, OutputInterface $output) {

		$this->output = $output;
		$member = $input->getArgument('member');

		/** @var UserRepository $userRepository */
		$userRepository = $this->entityManager->getRepository(User::class);

		/** @var ContactRepository $contactRepository */
		$contactRepository = $this->entityManager->getRepository(Contact::class);

		/** @var AppendixRepository $appendixRepository */
		$appendixRepository = $this->entityManager->getRepository(Appendix::class);

		if( $member )
			$users = $userRepository->findBy(['login'=>$member]);
		else
			$users = $userRepository->findBy(['type'=>['contact','company']],['login'=>'ASC']);

		foreach ($users as $user){

			if( $user->isLegalRepresentative() ){

				if(!$company = $user->getCompany() )
					continue;

				$search = '_PM'.$company->getId().'_';
			}
			else{

				if( !$contact = $user->getContact() )
					continue;

				$search = ['_PP'.$contact->getId().'_', '_PP'.$contact->getId().'.'];
			}

			$this->output->writeln('Getting data from Eudonet for user '.$user->getId());

			$appendices = $this->eudonet->getAppendices($search);

			//todo: find a generic way to handle undefined contact_id
			foreach ($appendices as $key=>$appendix){
				if( $appendix['contact_id'] && !$contactRepository->find($appendix['contact_id']) )
					unset($appendices[$key]);
			}

			$this->output->writeln('Inserting to database');
			$appendixRepository->bulkInserts($appendices);

			$this->output->writeln("<info>".count($appendices)." item updated</info>");
		}

		$this->output->writeln("<info>Sync completed</info>");

		return 1;
	}
}