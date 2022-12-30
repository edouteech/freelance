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
use App\Repository\OptionRepository;

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

class ImportCommand extends AbstractCommand {

	/**
	 * Configure
	 */
	protected function configure () {

		$this->setName('app:import');
		$this->setDescription("Import csv to database");

		$this->addArgument('className', InputArgument::OPTIONAL, 'Entity className');
		$this->addArgument('property', InputArgument::OPTIONAL, 'Entity property');
		$this->addArgument('column', InputArgument::OPTIONAL, 'Column id in csv');
		$this->addArgument('file', InputArgument::OPTIONAL, 'CSV File path');
	}

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
	public function execute (InputInterface $input, OutputInterface $output) {

		$className = $input->getArgument('className');
		$property = $input->getArgument('property');
		$column = $input->getArgument('column');
		$file = $input->getArgument('file');

		try {

            $output->writeln('<question>Starting '.$className.' import</question>');
            $items = [];
            $row = 0;

            if ( ($handle = fopen($file, "r")) !== FALSE ) {

                while (($data = fgetcsv($handle, 1000, ";")) !== FALSE){

                    if( $row )
                        $items[$data[0]] = utf8_encode($data[$column]);

                    $row++;
                }

                fclose($handle);
            }

            if( count($items) ){

                $count = count($items);
                $processed = $i = 0;
                $progress = -1;
                $batchSize = 20;
                $entityRepository = $this->entityManager->getRepository('App\Entity\\'.ucfirst($className));
                $method = 'set'.ucfirst($property);

                $output->writeln('Inserting to database');

                foreach ($items as $id=>$value){

                    if( $entity = $entityRepository->find($id) ){

                        if( $processed || method_exists($entity, $method) ){

                            if( $value ){

                                $entity->$method($value);
                                $this->entityManager->persist($entity);

                                if (($processed % $batchSize) === 0) {
                                    $this->entityManager->flush();
                                    $this->entityManager->clear();
                                }

                                $processed++;
                            }
                        }
                    }

                    $_progress = round(($i/$count)*100);
                    if( $progress != $_progress){

                        $progress = $_progress;
                        echo "\033[5D".str_pad($progress, 3, ' ', STR_PAD_LEFT) . " %";
                    }

                    $i++;
                }

                $this->entityManager->flush();
                $this->entityManager->clear();

                $output->writeln("<info>".$processed." item updated</info>");
            }
            else{

                $output->writeln("<info>Zero updated</info>");
            }
		}
		catch (Throwable $t){

			$this->error($t->getMessage(), false);
		}

		$output->writeln("<info>Import completed</info>");

		return 1;
	}
}