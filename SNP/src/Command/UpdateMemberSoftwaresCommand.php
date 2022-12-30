<?php

namespace App\Command;

use App\Traits\TimeTrait;
use App\Service\FtpService;
use App\Service\EudonetConnector;
use Symfony\Component\Mime\Email;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

class UpdateMemberSoftwaresCommand extends Command
{
    use TimeTrait;

    protected static $defaultName = 'app:maj:logiciels';
    protected static $defaultDescription = '';

    private $parameterBag;
    private $eudonetConnector;
    // private $mailer;

    public function __construct(
        ParameterBagInterface $parameterBag,
        EudonetConnector $eudonetConnector
        // MailerInterface $mailer
    ) {
        parent::__construct(self::$defaultName);

        $this->parameterBag = $parameterBag;
        $this->eudonetConnector = $eudonetConnector;
        // $this->mailer = $mailer;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stopwatch = new Stopwatch();
        $stopwatch->start('UpdateMemberSoftwares');

        $filesystem = new Filesystem();

        $memberSoftwaresFilepath = sprintf('%s/var/cron/adherent/files/logiciels_adherents_acheter_louer.csv', $this->parameterBag->get('kernel.project_dir'));

        if ($filesystem->exists($memberSoftwaresFilepath)) {
            $filesystem->remove($memberSoftwaresFilepath);
        }

        $snpiFtpService = new FtpService(['host' => 'ftp.snpi.com', 'login' => 'snpial', 'password' => 'UhBvEjixKzQTTBix']);

        if (! $recoveredFilepath = $snpiFtpService->get('/logiciels/stats-adherent-passerelle.csv')) {
            $io->error("Erreur lors de la récupération du fichier stats-adherent-passerelle.csv");
            return 1;
        }

        $filesystem->rename($recoveredFilepath, $memberSoftwaresFilepath);
        unset($snpiFtpService, $recoveredFilepath);

        if (! $csvData = file_get_contents($memberSoftwaresFilepath)) {
            $io->error("Erreur lors de la récupération du fichier logiciels_adherents_acheter_louer.csv");
            return 1;
        }

        $csvData = utf8_encode($csvData);
        $csvData = str_replace("\"", " ", $csvData);
        $csvData = trim($csvData);
	    $csvData = preg_replace("~[[:blank:]]{2,}~", " ", $csvData);

        $rows = explode("\n", $csvData);
        $totalRows = count($rows);

        if ($totalRows == 0) {
            $io->error('Script non écécuté, vérifier le fichier CSV / TXT');

            // $this->mailer->send(
            //     (new Email())
            //         ->to('denise@snpi.fr')
            //         ->subject('Script non éxécuté')
            //         ->text(sprintf('console/bin %s, vérifier le fichier CSV', self::$defaultName))
            // );

            return 1;
        }

        $progressBar = $io->createProgressBar();

        foreach ($rows as $row) {
            if (! empty($row)) {
                $columns = explode(";", $row);
                $memberId = $columns[0];
                $software = $columns[2];
                $acheterLouerId = $columns[2];

                $companiesResult = $this->eudonetConnector->execute(
                    $this->eudonetConnector->createQueryBuilder()
                        ->select('company')
                        ->where('member_id', '=', $memberId)
                );

                if (! $companiesResult) {
                    $io->warning('Fiche inexistante: ' . $memberId);
                    $progressBar->advance();
                    continue;
                }

                $softwareIds = Yaml::parseFile(
                    sprintf('%s/config/software_ids.yaml', $this->parameterBag->get('kernel.project_dir'))
                );

                $softwareId = isset($softwareIds[$software]) ? $softwareIds[$software] : 1601;

                $this->eudonetConnector->execute(
                    $this->eudonetConnector->createQueryBuilder()
                        ->update('company')
                        ->setValues([
                            'acheter_louer_id' => $acheterLouerId,
                            'software' => $softwareId
                        ])
                        ->where('id', '=', $companiesResult[0]['FileId'])
                );
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        $event = $stopwatch->stop('UpdateMemberSoftwares');

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
