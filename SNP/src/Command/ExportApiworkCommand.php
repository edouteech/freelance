<?php

namespace App\Command;

use SimpleXMLElement;
use App\Traits\TimeTrait;
use App\Service\EudonetConnector;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ExportApiworkCommand extends Command
{
    use TimeTrait;

    protected static $defaultName = 'app:export:apiwork';
    protected static $defaultDescription = '';

    private $parameterBag;
    private $eudonetConnector;

    public function __construct(ParameterBagInterface $parameterBag, EudonetConnector $eudonetConnector)
    {
        parent::__construct(self::$defaultName);

        $this->parameterBag = $parameterBag;
        $this->eudonetConnector = $eudonetConnector;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stopwatch = new Stopwatch();
        $stopwatch->start('ExportApiwork');

        $agenciesResult = $this->eudonetConnector->execute(
            $this->eudonetConnector->createQueryBuilder()
                ->select(
                    'm.member,
                    co.password, co.brand, co.name, co.franchise, co.siren, co.street1, co.zip, co.city, co.phone, co.email, 
                    co.can_create_account, co.website, co.logo, co.software, co.status, co.is_franchise, co.archived,
                    cont.civility, cont.lastname, cont.firstname,
                    co_r.archived'
                )
                ->from('company_representative', 'co_r')
                ->join('company', 'co')
                ->join('contact', 'cont')
                ->join('membership', 'm')
                ->where('co.status', '=', 1000003)
                ->andWhere('m.member', '!=', 2)
                ->andWhere('co.archived', '=', false)
                ->andWhere('co_r.archived', '=', false)
                ->orderBy('m.member', 'ASC')
        );

        if ($agenciesResult) {
            $apiworkFilepath = sprintf('%s/var/apiwork/export_apiwork.yaml', $this->parameterBag->get('kernel.project_dir'));

            $filesystem = new Filesystem();

            if ($filesystem->exists($apiworkFilepath)) {
                $filesystem->remove($apiworkFilepath);
            }

            $agencesXml = new SimpleXMLElement('<agences>');

            foreach ($agenciesResult as $agency) {
                $agencyXml = $agencesXml->addChild('agence');
				
				$logo = ($agency["Fields"]["356"]["DbValue"] != "" ? "https://secure.snpi.pro/img/logos/" . $agency["Fields"]["356"]["DbValue"] : "");
                
                $agencyXml->addChild('id', $agency["Fields"]["322"]["DbValue"]);
                $agencyXml->addChild('passwd', $agency["Fields"]["353"]["DbValue"]);
                $agencyXml->addChild('enseigne', $agency["Fields"]["312"]["DbValue"]);
                $agencyXml->addChild('raison_sociale', $agency["Fields"]["301"]["DbValue"]);
                $agencyXml->addChild('franchise', $agency["Fields"]["338"]["DbValue"]);
                $agencyXml->addChild('rcs', $agency["Fields"]["318"]["DbValue"]);
                $agencyXml->addChild('civilite', $agency["Fields"]["205"]["DbValue"]);
                $agencyXml->addChild('nom', $agency["Fields"]["201"]["DbValue"]);
                $agencyXml->addChild('prenom', $agency["Fields"]["202"]["DbValue"]);
                $agencyXml->addChild('adresse', $agency["Fields"]["302"]["DbValue"]);
                $agencyXml->addChild('cp', $agency["Fields"]["309"]["DbValue"]);
                $agencyXml->addChild('ville', $agency["Fields"]["310"]["DbValue"]);
                $agencyXml->addChild('tel', $agency["Fields"]["305"]["DbValue"]);
                $agencyXml->addChild('fax', $agency["Fields"]["306"]["DbValue"]);
                $agencyXml->addChild('email', $agency["Fields"]["323"]["DbValue"]);
                $agencyXml->addChild('website', $agency["Fields"]["322"]["DbValue"]);
                $agencyXml->addChild('logo', $logo);
                $agencyXml->addChild('has_rcp_snpi', 0);
                $agencyXml->addChild('has_gf_snpi', 0);
                $agencyXml->addChild('logiciel', $agency["Fields"]["340"]["DbValue"]);
            }

            $file = fopen($apiworkFilepath, 'w');
            fwrite($file, $agencesXml->asXML());
            fclose($file);
        }

        $event = $stopwatch->start('ExportApiwork');

        $io->success(
            sprintf(
                "Script éxécuté\nDebut: %s\nFin: %s\nDuree d'execution du script: %s",
                date("d/m/Y H:i:s", $event->getStartTime() / 1000),
                $this->formatMilliseconds($event->getDuration())
            )
        );

        return 0;
    }
}
