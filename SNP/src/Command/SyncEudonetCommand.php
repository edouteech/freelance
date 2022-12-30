<?php

namespace App\Command;

use App\Entity\Address;
use App\Entity\Agreement;
use App\Entity\Appendix;
use App\Entity\Company;
use App\Entity\CompanyBusinessCard;
use App\Entity\Contact;
use App\Entity\Formation;
use App\Entity\FormationCourse;
use App\Entity\CompanyRepresentative;
use App\Entity\Contract;
use App\Entity\FormationParticipant;
use App\Entity\FormationPrice;
use App\Entity\Instructor;
use App\Entity\Mail;
use App\Entity\Option;

use App\Entity\Sync;
use App\Repository\OptionRepository;

use App\Service\EudonetAction;
use App\Service\EudonetConnector;
use App\Service\EudonetQueryBuilder;
use App\Service\Ping;
use DateTime;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Throwable;

class SyncEudonetCommand extends AbstractCommand {

	/** @var EudonetConnector $eudonet */
	private $eudonet;
    /** @var EudonetAction $eudonet */
	private $eudonetAction;
	private $ping;

	private $last_sync=false;
	private $scratch=false;
	private $condition=false;
	private $table=false;


	public function __construct (ContainerInterface $container, EudonetConnector $eudonet, EudonetAction $eudonetAction, Ping $ping, LoggerInterface $logger) {

		parent::__construct($container, $logger);

		$this->eudonet = $eudonet;
		$this->eudonetAction = $eudonetAction;
		$this->ping = $ping;
	}

	/**
	 * Configure
	 */
	protected function configure () {

		$this->setName('app:sync:eudonet');
		$this->setDescription("Synchronize companies, contacts, addresses from Eudonet");

		$this->addArgument('scratch', InputArgument::OPTIONAL, 'Import from scratch ?');
		$this->addArgument('table', InputArgument::OPTIONAL, 'Import specific table');
		$this->addArgument('condition', InputArgument::OPTIONAL, 'Add condition');
	}

	/**
	 * @param EudonetQueryBuilder $qb
	 * @throws Exception
	 */
	private function addCondition(EudonetQueryBuilder &$qb){

		if( $this->condition ){

			$condition = explode(' ', $this->condition);

			if( count($condition) == 3 ){

				if( $condition[2] == '""' ||  $condition[2] == "''")
					$condition[2] = "";

				$qb->andWhere($condition[0], $condition[1], $condition[2]);
			}
		}
	}

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     * @throws InvalidArgumentException
     * @throws ExceptionInterface
     */
	public function execute (InputInterface $input, OutputInterface $output) {

		$this->eudonet->getMetaInfos(300, [301]);

		ini_set('xdebug.max_nesting_level', 9999);

        $this->output = $output;
		$this->scratch = $input->getArgument('scratch');
		$this->table = $input->getArgument('table');
		$this->condition = $input->getArgument('condition');

		/** @var OptionRepository $optionRepository */
		$optionRepository = $this->entityManager->getRepository(Option::class);

        /** @var OptionRepository $optionRepository */
		$syncRepository = $this->entityManager->getRepository(Sync::class);
		$sync = $syncRepository->start('eudonet');

		$is_syncing = $optionRepository->get('eudo_is_syncing');
		$this->last_sync = $optionRepository->get('eudo_last_sync');

		if( $is_syncing ){

			$last_sync = new DateTime($this->last_sync);
			$thirty_minutes_ago = new DateTime("now - 30 minutes");

			if( $thirty_minutes_ago->getTimestamp() < $last_sync->getTimestamp() )
				$this->error('Synchronisation is in progress');
		}

		if( $this->scratch )
			$this->last_sync = false;
		else
            $optionRepository->set('eudo_is_syncing', true);

        $last_sync = date("Y-m-d H:i:s");

		try {

			$this->syncCompanies();
			$this->syncCompaniesBusinessCard();
			$this->syncContacts();
			$this->syncAddresses();
			$this->syncMails();
			$this->syncCompaniesRepresentatives();
			$this->syncFormations();
			$this->syncFormationsCourses();
			$this->syncFormationsPrice();
            $this->syncAgreements();
            $this->syncFormationsParticipants();
			$this->syncInstructors();
			//$this->syncAppendices();
			//$this->syncContracts();

			if( !$this->table ){

				$optionRepository->set('eudo_last_sync', $last_sync);
				$this->ping->ping($_ENV['PING_SYNC_EUDONET']);
			}
		}
		catch (Throwable $t){

			$this->error($t->getMessage(), false);
		}

		$optionRepository->set('eudo_is_syncing', false);
		$syncRepository->end($sync);

		$this->output->writeln("<info>Sync completed</info>");

		return 1;
	}


	/**
	 * @return void
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	private function syncCompanies() {

		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('company','c')
			->where('c.brand', '!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('c.updated_at', '>', $this->last_sync)->orWhere('c.created_at', '>', $this->last_sync));

		$qb->orderBy('created_at');

		$this->process($qb, Company::class);
	}


	/**
	 * @return void
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	private function syncMails() {

		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('mail','m')
			->join('company', 'c')
			->where('c.brand', '!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('m.updated_at', '>', $this->last_sync)->orWhere('m.created_at', '>', $this->last_sync));

		$qb->orderBy('created_at');

		$this->process($qb, Mail::class);
	}


	/**
	 * @return void
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	private function syncCompaniesBusinessCard() {

		$exp = $this->eudonet->createExpressionBuilder();

		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('company_business_card', 'b')
			->join('company', 'c')
			->where('c.brand', '!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('b.updated_at', '>', $this->last_sync)->orWhere('b.created_at', '>', $this->last_sync));

		$qb->orderBy('b.created_at');

		$this->process($qb, CompanyBusinessCard::class);
	}


	/**
	 * @return void
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	private function syncCompaniesRepresentatives() {

		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('company_representative', 'l')
			->join('company', 'c')
			->join('contact', 'ct')
			->where('c.brand', '!=', '')
			->andWhere('ct.lastname','!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('l.updated_at', '>', $this->last_sync)->orWhere('l.created_at', '>', $this->last_sync));

		$qb->orderBy('l.created_at');

		$this->process($qb, CompanyRepresentative::class);
	}


	/**
	 * @return void
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	private function syncFormationsParticipants() {

		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('formation_participant', 'p')
			->join('contact', 'ct')
			->join('formation_course', 'fc')
			->where('ct.lastname','!=', '')
			->andWhere('fc.session','!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('p.updated_at', '>', $this->last_sync)->orWhere('p.created_at', '>', $this->last_sync));

		$qb->orderBy('p.created_at');

		$this->process($qb, FormationParticipant::class);
	}


	/**
	 * @return void
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	private function syncFormations() {

		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('formation', 'f')
			->where('f.title','!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('f.updated_at', '>', $this->last_sync)->orWhere('f.created_at', '>', $this->last_sync));

		$qb->orderBy('f.created_at');

		$this->process($qb, Formation::class);
	}


	/**
	 * @return void
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	private function syncFormationsPrice() {

		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('formation_price', 'fp')
			->join('formation', 'f')
			->where('fp.price','!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('fp.updated_at', '>', $this->last_sync)->orWhere('fp.created_at', '>', $this->last_sync));

		$qb->orderBy('fp.created_at');

		$this->process($qb, FormationPrice::class);
	}


	/**
	 * @return void
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	private function syncFormationsCourses() {

		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('formation_course', 'fc')
			->join('formation', 'f')
			->where('f.title','!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('fc.updated_at', '>', $this->last_sync)->orWhere('fc.created_at', '>', $this->last_sync));

		$qb->orderBy('fc.created_at');

		$this->process($qb, FormationCourse::class);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	private function syncContacts () {

		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('contact', 'c')
			->where('c.lastname','!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('c.updated_at', '>', $this->last_sync)->orWhere('c.created_at', '>', $this->last_sync));

		$qb->orderBy('c.created_at');

		$this->process($qb, Contact::class);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	private function syncAddresses () {

		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('address', 'a')
			->join('company', 'c')
			->join('contact', 'ct')
			->where('ct.lastname','!=', '')
			->andSubWhere($exp->where('c.name', '!=', '')->orWhere('a.is_home', '=', true));


		if( $this->last_sync )
			$qb->andSubWhere($exp->where('a.updated_at', '>', $this->last_sync)->orWhere('a.created_at', '>', $this->last_sync));

		$qb->orderBy('a.created_at');

		$this->process($qb, Address::class);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function syncAgreements()
	{
		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('agreement', 'a')
			->join('company', 'cm')
			->join('contact', 'ct')
			->join('formation_course', 'fc')
			->where('ct.lastname', '!=', '')
			->andWhere('fc.session', '!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('a.updated_at', '>', $this->last_sync)->orWhere('a.created_at', '>', $this->last_sync));

		$qb->orderBy('a.created_at');

		$this->process($qb, Agreement::class);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	public function syncInstructors()
	{
		$exp = $this->eudonet->createExpressionBuilder();
		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('instructor', 'i')
			->join('formation', 'f')
			->join('contact', 'c')
			->where('c.lastname', '!=', '')
			->andWhere('f.title', '!=', '');

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('i.updated_at', '>', $this->last_sync)->orWhere('i.created_at', '>', $this->last_sync));

		$qb->orderBy('i.created_at');

		$this->process($qb, Instructor::class);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	public function syncAppendices()
	{
        $appendices = $this->eudonetAction->getAppendices(false, $this->last_sync);

        $appendixRepository = $this->entityManager->getRepository(Appendix::class);
        $appendixRepository->bulkInserts($appendices, 1, false);
	}


	/**
	 * @return void
	 * @throws Exception
	 */
	public function syncContracts()
	{
		$exp = $this->eudonet->createExpressionBuilder();

		$now = new DateTime();
		$now->modify('3 years ago');

		$qb = $this->eudonet->createQueryBuilder()
			->select('*')
			->from('contract', 'c')
			->join('company', 'cm')
			->join('contact', 'ct')
			->where('ct.lastname', '!=', '')
			->andWhere('c.created_at', '>', $now->format('Y-m-d'));

		if( $this->last_sync )
			$qb->andSubWhere($exp->where('c.updated_at', '>', $this->last_sync)->orWhere('c.created_at', '>', $this->last_sync));

		$qb->orderBy('c.created_at');

		$this->process($qb, Contract::class);
	}


	/**
	 * @param EudonetQueryBuilder $qb
	 * @param string $className
	 * @return array
	 * @throws InvalidArgumentException
	 * @throws ExceptionInterface
	 */
	private function process ($qb, $className, $insert=true) {

		if( $this->table && $this->table != $qb->getTableName())
			return [];

		$optionRepository = $this->entityManager->getRepository(Option::class);

		if( $optionRepository->get('eudo_call_remain') < 400 )
			$this->error('Eudonet quota too low');

		$this->addCondition($qb);

		$this->output->writeln('<question>Starting '.$className.' synchronisation</question>');

		$this->output->writeln('Getting data from Eudonet');
		$items = $this->eudonet->execute($qb, $this->scratch?864000:0);

		if( $items && count($items) && $insert ){

			$entityRepository = $this->entityManager->getRepository($className);
			$this->output->writeln('Inserting to database');

			$entityRepository->bulkInserts($items, 20, true, $this->logger);
			$this->output->writeln("<info>".count($items)." item updated</info>");
		}
		else{

			$this->output->writeln("<info>Zero updated</info>");
		}

        return $items;
	}
}
