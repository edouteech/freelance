<?php

namespace App\Command;


use App\Entity\FormationCourse;
use App\Entity\Signature;

use App\Repository\FormationCourseRepository;
use App\Repository\SignatureRepository;
use App\Service\ContraliaAction;
use App\Service\EudonetAction;
use App\Service\Mailer;
use DateTime;
use Doctrine\ORM\ORMException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class FinalizeSignaturesCommand extends AbstractCommand {

	/** @var EudonetAction $eudonet */
	private $eudonet;
	private $contralia;
	private $mailer;


	public function __construct (ContainerInterface $container, EudonetAction $eudonet, ContraliaAction $contralia, LoggerInterface $logger, Mailer $mailer) {

		parent::__construct($container, $logger);

		$this->eudonet = $eudonet;
		$this->contralia = $contralia;
		$this->mailer = $mailer;
	}

	/**
	 * Configure
	 */
	protected function configure () {

		$this->setName('app:signature:finalize');
		$this->setDescription("Finalize expired signature");
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

		/** @var SignatureRepository $signatureRepository */
		$signatureRepository = $this->entityManager->getRepository(Signature::class);
		$signatures = $signatureRepository->findAllExpired(FormationCourse::class);

		foreach ($signatures as $signature){

			try {

				$this->contralia->terminate($signature);

				if( $filepath = $this->contralia->getFinalDoc($signature, 'timesheet') )
					$this->eudonet->uploadFile('formation_course', $signature->getEntityId(), null, null, $filepath, 'AGENCE_FORMATION_ELEARNING_EMARGEMENT');

			} catch (Exception $e) {

				$this->error($e->getMessage(), false);

				if( $e->getMessage() == 'Some signatories have not signed the transaction' ){

					if( $filepath = $this->contralia->getCurrentDoc($signature, 'timesheet') )
						$this->eudonet->uploadFile('formation_course', $signature->getEntityId(), null, null, $filepath, 'AGENCE_FORMATION_ELEARNING_EMARGEMENT');
				}

				if( !$signature->getAlertedAt() ){

					/** @var FormationCourseRepository $formationCourseRepository */
					$formationCourseRepository = $this->entityManager->getRepository(FormationCourse::class);

					if( $formationCourse = $formationCourseRepository->find($signature->getEntityId()) ){

						$formation = $formationCourse->getFormation();
						$signatories = $signature->getSignatories();

						$signatories_left = [];

						foreach ($signatories as $signatory){

							if( $signatory->getStatus() != 'signed' ){

								$address = $signatory->getAddress();
								$contact = $address->getContact();

								$signatories_left[] = ['contact'=>$contact, 'address'=>$address];
							}
						}

						if( count($signatories_left) ){

							$body = $this->mailer->createBodyMail('e-learning/unsigned-report.html.twig', ['formationCourse'=>$formationCourse, 'formation'=>$formation, 'signatories_left'=>$signatories_left]);
							$this->mailer->sendMessage($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], count($signatories_left)." participant(s) n'ont pas signé la feuille de présence" , $body);

							$this->output->writeln("<info>SNPI alerted for formation ".$formation->getTitle()."</info>");
						}
						else{

							$body = $this->mailer->createBodyMail('e-learning/unsigned-error.html.twig', ['formationCourse'=>$formationCourse, 'formation'=>$formation, 'error'=>$e->getMessage()]);
							$this->mailer->sendMessage($_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'], "Une erreur est survenue lors de la cloture de la feuille de présence" , $body);

							$this->output->writeln("<info>SNPI alerted for error</info>");
						}
					}

					$signature->setAlertedAt(new DateTime());
					$signatureRepository->save($signature);
				}
			}
		}

		$this->output->writeln("<info>Signature terminated</info>");

		return 1;
	}
}