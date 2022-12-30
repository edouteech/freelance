<?php

namespace App\Command;

use App\Traits\TimeTrait;
use App\Service\FtpService;
use App\Repository\ContactRepository;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ExportBieniciCommand extends Command
{
    use TimeTrait;

    protected static $defaultName = 'app:export:bienici';
    protected static $defaultDescription = '';

    private $parameterBag;
    private $contactRepository;

    public function __construct(ParameterBagInterface $parameterBag, ContactRepository $contactRepository)
    {
        parent::__construct(self::$defaultName);

        $this->parameterBag = $parameterBag;
        $this->contactRepository = $contactRepository;
    }

    protected function configure(): void
    {
        $this->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stopwatch = new Stopwatch();
        $stopwatch->start('ExportBienici');

        $filesystem = new Filesystem();

        $bieniciFilepath = sprintf('%s/var/cron/adherent/files/export_bienici/export_bienici.csv', $this->parameterBag->get('kernel.project_dir'));

        if ($filesystem->exists($bieniciFilepath)) {
            $filesystem->remove($bieniciFilepath);
        }

        $snpiAdvertArchiveFilepath = sprintf('%s/var/cron/adherent/files/export_bienici/snpi_annonces.zip', $this->parameterBag->get('kernel.project_dir'));

        if ($filesystem->exists($snpiAdvertArchiveFilepath)) {
            $filesystem->remove($snpiAdvertArchiveFilepath);
        }

        // Récupération de l'archive d'Olivier et balancage chez Bienici
        $snpiFtpService = new FtpService(['host' => 'ftp.snpi.com', 'login' => 'immotransfer', 'password' => 'qFFCMaxDjN']);

        if (! $recoveredFilepath = $snpiFtpService->get('/snpi.zip')) {
            $io->error("Erreur lors de la récupération de l'archive des biens (AL).");
            return 1;
        }

        $filesystem->rename($recoveredFilepath, $snpiAdvertArchiveFilepath);
        unset($snpiFtpService, $recoveredFilepath);

        // Upload du fichier d'annonces sur Bien'ici
        $bieniciFtpService = new FtpService(['host' => 'ftp.bienici.com', 'login' => 'snpi', 'password' => '31VQ0VE4p777xmKH']);

        if (! $bieniciFtpService->put('/snpi_annonces.zip', $snpiAdvertArchiveFilepath)) {
            $io->error("Erreur lors de l'upload FTP {1}.");
            return 1;
        }

        $filesystem->remove($snpiAdvertArchiveFilepath);
        unset($snpiAdvertArchiveFilepath);

        // Génération du fichier annuaire adhérents
        $membersDirectoryFilepath = sprintf('%s/var/cron/adherent/files/logiciels_adherents_acheter_louer.csv', $this->parameterBag->get('kernel.project_dir'));

        if (! $filesystem->exists($membersDirectoryFilepath)) {
            $snpiFtpService = new FtpService(['host' => 'ftp.snpi.com', 'login' => 'snpial', 'password' => 'UhBvEjixKzQTTBix']);

            if (! $recoveredFilepath = $snpiFtpService->get('/logiciels/stats-adherent-passerelle.csv')) {
                $io->error("Erreur lors de la récupération du fichier stats-adherent-passerelle.csv");
                return 1;
            }

            $filesystem->rename($recoveredFilepath, $membersDirectoryFilepath);
            unset($snpiFtpService, $recoveredFilepath);
        }

        if (! $csvData = file_get_contents($membersDirectoryFilepath)) {
            $io->error("Erreur lors de la récupération du fichier logiciels_adherents_acheter_louer.csv");
            return 1;
        }

        $csvData = utf8_encode($csvData);
        $csvData = str_replace("\"", " ", $csvData);
        $csvData = trim($csvData);
	    $csvData = preg_replace("~[[:blank:]]{2,}~", " ", $csvData);

        $rows = explode("\n", $csvData);
        $membersId = [];

        if (count($rows) > 500) {
            foreach ($rows as $row) {
                if (! empty($row)) {
                    $columns = explode(";", $row);
                    
                    $membersId[] = $columns[0];
                }
            }
        }

        if (count($membersId) > 500) {
            if (! $filesystem->exists($bieniciFilepath)) {
                $filesystem->remove($bieniciFilepath);
            }

            $file = fopen($bieniciFilepath, 'w');

            $header = [
                'techId',
                'name',
                'corporateName',
                'street',
                'postalCode',
                'city',
                'rcs',
                'cardNumber',
                'contactFirstName',
                'contactLastName',
                'phone',
                'fax',
                'email',
                'logoUrl',
                'saleEmail',
                'salePhone',
                'rentalEmail',
                'rentalPhone',
                'website'
            ];

            fputs($file, '"' . implode('";"', $header) . '"');
            rewind($file);

            $contacts = $this->contactRepository->findByMembersId($membersId);

            foreach ($contacts as $contact) {
                $address = $contact->getAddress();
                $company = $address->getCompany();
                $logo = $company->getLogo();
                $legalRepresentative = $company->getLegalRepresentative();

                $line = implode(
                    '";"',
                    [
                        $contact->getMemberId(),
                        $company->getName(),
                        '',
                        $address->getStreet1(),
                        $address->getZip(),
                        $address->getCity(),
                        '',
                        '',
                        $legalRepresentative->getFirstname(),
                        $legalRepresentative->getLastname(),
                        $company->getPhone(),
                        $company->getFax(),
                        $company->getEmail(),
                        $logo != '' ? 'https://secure.snpi.pro/img/logos/' . $logo : '',
                        $contact->getEmail(),
                        $contact->getPhone(),
                        $address->getEmail(),
                        $address->getPhone(),
                        $company->getWebsite()
                    ]
                );

                fputs(
                    $file,
                    '"' . $line . '"'
                );
                rewind($file);
            }

            fclose($bieniciFilepath);
        }

        if (! $bieniciFtpService->put('/snpi_annuaire.csv', $bieniciFilepath)) {
            $io->error("Erreur lors de l'upload FTP {2}.");
            return 1;
        }


        $event = $stopwatch->stop('ExportBienici');

        $io->success(
            sprintf(
                "Debut: %s\nFin: %s\nDuree d'execution du script: %s",
                date("d/m/Y H:i:s", $event->getStartTime() / 1000),
                $this->formatMilliseconds($event->getDuration())
            )
        );

        return 0;
    }

    private function formatMilliseconds($milliseconds)
    {
        $seconds = floor($milliseconds / 1000);
        $minutes = floor($seconds / 60);
        $hours = floor($minutes / 60);
        $milliseconds = $milliseconds % 1000;
        $seconds = $seconds % 60;
        $minutes = $minutes % 60;

        return sprintf('%dH%dm%d', $hours, $minutes, $hours);
    }
}
