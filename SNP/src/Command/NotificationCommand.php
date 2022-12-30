<?php

namespace App\Command;

use App\Entity\News;
use App\Entity\Notification;
use App\Service\Mailer;
use App\Service\WonderpushConnector;
use DateTime;
use Exception;
use App\Entity\Company;
use App\Repository\CompanyRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NotificationCommand extends AbstractCommand
{
    private $mailer;
    private $wonderpushConnector;

	/**
	 * @param ContainerInterface $container
	 * @param Mailer $mailer
	 * @param LoggerInterface $logger
	 * @param WonderpushConnector $wonderpushConnector
	 */
	public function __construct (ContainerInterface $container, Mailer $mailer, LoggerInterface $logger, WonderpushConnector $wonderpushConnector) {

        parent::__construct($container, $logger);

        $this->wonderpushConnector = $wonderpushConnector;
        $this->mailer = $mailer;
    }

    /**
     * Configure
     */
    protected function configure()
    {
        $this->setName('app:notification:send');
        $this->setDescription("Send notifications");
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $this->sendCanCreateAccount();
        $this->sendNewsNotifications();
    }


    public function sendNewsNotifications()
    {
        $this->output->writeln("<info>Starting notifications for news publication</info>");

        $newsRepository = $this->entityManager->getRepository(News::class);

        $news = $newsRepository->findUnotified('app');

        foreach ($news as $_news){

            if( $_news->getNotified() )
                continue;

            $this->output->writeln("<info>Starting notifications for ".$_news->getTitle()."</info>");

            try {

                $tags = [];

                if( $_news->getRole() != 'ROLE_CLIENT' && $_news->getRole() != 'ROLE_USER' )
                    $tags[] = '+'.strtolower(str_replace('ROLE_','', $_news->getRole()));

                foreach ($_news->getTerms('news_category') as $term)
                    $tags[] = '+'.$term->getSlug();

                $targetUrl = false;

                if( $_news->getLinkType() === 'external' )
                    $targetUrl = $_news->getLink();
                elseif( $_news->getLinkType() === 'document' )
                    $targetUrl = $_ENV['DASHBOARD_URL'].'/document/'.$_news->getSlug();
                elseif( $_news->getLinkType() === 'page' )
                    $targetUrl = $_ENV['DASHBOARD_URL'].'/edito/'.$_news->getSlug();
                elseif( $_news->getLinkType() === 'article' )
                    $targetUrl = $_ENV['DASHBOARD_URL'].'/news/'.$_news->getSlug();

                $params = [
                    'title'=>'Actualité',
                    'text'=>$_news->getTitle(),
                    'attachment'=>$_news->getThumbnail()
                ];

                if( $targetUrl )
                    $params['targetUrl'] = $targetUrl;

                if( empty($tags) )
                    throw new Exception('La catégorie est vide');

                $this->wonderpushConnector->createDelivery($params, ['tags'=>$tags]);

                $_news->setNotified(true);
                $newsRepository->save($_news);

            } catch (Exception $e) {

                $this->mailer->sendAlert($_ENV['ALERT_EMAIL'], 'Notification impossible pour l\actu "'.$_news->getTitle().'"', $e->getMessage());
                $this->error($e->getMessage(), false);
            }
        }
    }


    public function sendCanCreateAccount()
    {
        $this->output->writeln("<info>Starting notifications for account creation</info>");

        /**
         * @var CompanyRepository $companyRepository
         */
        $companyRepository = $this->entityManager->getRepository(Company::class);
        $notificationRepository = $this->entityManager->getRepository(Notification::class);

        $qbn = $notificationRepository->createQueryBuilder('n');
        $qbn->select('n.entityId')
            ->where('n.action = :action')
            ->andWhere('n.entityType = :entityType');

        $qbc = $companyRepository->createQueryBuilder('c');

        $qbc->where('c.canCreateAccount = 1 ')
            ->andWhere($qbc->expr()->not($qbc->expr()->exists($qbn->getDQL())));

        $qbc->setParameter('action', 'can_create_account')
            ->setParameter('entityType', Company::class);

        $companies = $qbc->getQuery()->getResult();

        /* @var Company[] $companies */
        foreach ($companies as $company){

            $legalRepresentatives = $company->getLegalRepresentatives();

            foreach ($legalRepresentatives as $contact){

                if( $email = $contact->getEmail($company) ){

                    $body = $this->mailer->createBodyMail('account/invite-legal.html.twig', ['title'=>'Activez dès aujourd’hui la multi-connexion', 'contact'=>$contact, 'company'=>$company]);
                    $this->mailer->sendMessage($email, 'Activez dès aujourd’hui la multi-connexion', $body);

                    $this->output->writeln("<info>".$email." notified</info>");
                }
            }

            $notificationRepository->create('can_create_account', $company);
        }
    }


}