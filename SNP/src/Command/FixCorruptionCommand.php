<?php

namespace App\Command;

use App\Entity\Address;
use App\Entity\Agreement;
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
use App\Repository\AddressRepository;
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

class FixCorruptionCommand extends AbstractCommand {

    /** @var EudonetAction $eudonet */
    private $eudonet;

    public function __construct (ContainerInterface $container, EudonetAction $eudonet, LoggerInterface $logger) {

        parent::__construct($container, $logger);

        $this->eudonet = $eudonet;
    }

    /**
     * Configure
     */
    protected function configure () {

        $this->setName('app:fix:corruption');
        $this->setDescription("Parse addresses and fix corrupted email");
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     * @throws Exception
     */
    public function execute (InputInterface $input, OutputInterface $output) {

        $this->output = $output;

        /** @var OptionRepository $optionRepository */
        $optionRepository = $this->entityManager->getRepository(Option::class);

        $is_syncing = $optionRepository->get('eudo_is_syncing');

        if( !$is_syncing ){

            /** @var AddressRepository $addressRepository */
            $addressRepository = $this->entityManager->getRepository(Address::class);

            $addresses = $addressRepository->findAllWithEmail();
            $total = count($addresses);

            $this->output->writeln("<info>".$total." emails to check</info>");

            $count = 0;

            /** @var Address[] $addresses */
            foreach ($addresses as $address){

                if( $address->getEmail() == $address->getRawEmail() ){

                    $this->output->writeln("<info>Fixing ".$address->getContact()."...</info>");

                    $this->eudonet->pull($address);
                    $count++;
                }
            }

            $this->output->writeln("<info>".$count." fixed</info>");
        }
        else{

            $this->output->writeln("<info>Sync in progress</info>");
        }

        return 1;
    }
}