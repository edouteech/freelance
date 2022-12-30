<?php

namespace App\Command;

use DateTime;
use App\Service\Mailer;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\FormationInterestRepository;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Throwable;

class RemainingPlacesAlertCommand extends AbstractCommand
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var FormationInterestRepository
     */
    protected $formationInterestRepository;

    /**
     * @var Mailer
     */
    protected $mailer;

    public function __construct(EntityManagerInterface $entityManager, FormationInterestRepository $formationInterestRepository, Mailer $mailer, ContainerInterface $containe, LoggerInterface $consoleLogger)
    {
        parent::__construct($containe, $consoleLogger);

        $this->entityManager = $entityManager;
        $this->formationInterestRepository = $formationInterestRepository;
        $this->mailer = $mailer;

    }

    protected function configure()
    {
        $this
            ->setName('app:remaining-places-alert')
            ->setDescription('send an email to interested people when places become available')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $formationInterests = $this->formationInterestRepository->findByFormationCoursesHaveRemainingPlaces();

	    foreach ($formationInterests as $formationInterest) {

		    try {

			    $contact = $formationInterest->getContact();
			    $formationCourse = $formationInterest->getFormationCourse();
			    $formation = $formationCourse->getFormation();

			    if( $company = $formationInterest->getCompany() ){

				    $legalRepresentatives = $company->getLegalRepresentatives();

				    foreach ($legalRepresentatives as $legalRepresentative){

					    $body = $this->mailer->createBodyMail('formation/remaining.html.twig', ['title'=>'Une formation qui vous intéresse est à nouveau disponible !', 'formationCourse' => $formationCourse, 'formation' => $formation, 'contact' => $contact, 'legalRepresentative' => $legalRepresentative]);

					    if( $email = $legalRepresentative->getEmail($company) )
						    $this->mailer->sendMessage($email, 'Une formation qui vous intéresse est à nouveau disponible !', $body);
				    }
			    }
			    else{

				    $body = $this->mailer->createBodyMail('formation/remaining.html.twig', ['title'=>'Une formation qui vous intéresse est à nouveau disponible !', 'formationCourse' => $formationCourse, 'formation' => $formation, 'contact' => $contact]);

				    if( $email = $contact->getEmail() )
					    $this->mailer->sendMessage($email, 'Une formation qui vous intéresse est à nouveau disponible !', $body);
			    }

			    $formationInterest->setSendAt(new DateTime());

			    $this->formationInterestRepository->save($formationInterest);

		    } catch (Throwable $t) {

			    $this->error($t->getMessage());
		    }
	    }

	    $io->success('Les emails a été envoyé avec succès');

        return 0;
    }
}
