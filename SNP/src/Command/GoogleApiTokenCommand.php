<?php

namespace App\Command;

use Exception;
use App\Service\GoogleDriveService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GoogleApiTokenCommand extends Command
{
    protected static $defaultName = 'app:generate-google-api-token';
    private $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveService;
    }

    protected function configure()
    {
        $this->setDescription('Generate Google Api Token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {

            $client = $this->googleDriveService->getClient();

            $io->success("Google Api Token has been successfully generated: \n" . $client->getAccessToken()['access_token']);

        } catch (Exception $e) {

            $io->error($e->getMessage());

        }

        return 0;
    }
}
