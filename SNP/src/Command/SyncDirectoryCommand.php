<?php

namespace App\Command;

use Exception;
use App\Entity\Company;
use App\Repository\CompanyRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncDirectoryCommand extends AbstractCommand
{

    /**
     * Configure
     */
    protected function configure()
    {
        $this->setName('app:sync:directory');
        $this->setDescription("Synchronize directory to Acheter-Louer");
    }


	/**
	 * @param $message
	 * @return string
	 */
	public static function encrypt($message)
	{
		$nonceSize = openssl_cipher_iv_length('aes-256-ctr');
		$nonce = openssl_random_pseudo_bytes($nonceSize);

		$ciphertext = openssl_encrypt($message, 'aes-256-ctr', 'snpi.fr', OPENSSL_RAW_DATA, $nonce);

		return base64_encode($nonce.$ciphertext);
	}


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws Exception
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
	    /** 
         * @var CompanyRepository $companyRepository
         */
        $companyRepository = $this->entityManager->getRepository(Company::class);

        $io = new SymfonyStyle($input, $output);

        $formatedData = [
            'type' => 'FeatureCollection',
            'features' => []
        ];

        $companies = $companyRepository->findBy([
            'status' => 'member',
            'isHidden' => false
        ]);

        $i = 0;

        $companies_with_fee = [];
        $companies_with_infos = [];

        foreach ($companies as $company) {

            $contact = $company->getLegalRepresentative();

            if( !$company->getLatLng() )
            	continue;

	        $fee = $this->getPath('fee_directory').'/'.$company->getId().'.pdf';
            $hasFee = file_exists($fee);
            $hasInfo = !empty($company->getPhone()) || !empty($company->getEmail());

	        $formatedData['features'][$company->getId()] = [
		        'type' => 'Feature',
		        'id' => $company->getId(),
		        'geometry' => [
			        'type' => 'Point',
			        'coordinates' => [$company->getLng(), $company->getLat()]
		        ],
		        'properties' => [
			        "idAL" => $company->getAcheterLouerId()?$company->getAcheterLouerId():false,
			        "name" => $company->getName(),
			        "logo" => $this->exists('logo_directory', $company->getLogo())?$company->getLogo():false,
			        "administrator" => $company->getIsEstateManager(),
			        "manager" => $company->getIsPropertyManager(),
			        "houseagent" => $company->getIsDealer(),
			        "expert" => $company->getIsExpert(),
			        "address" => $company->getStreet(),
			        "postal_code" => $company->getZip(),
			        "contact" => (!is_null($contact) ? sprintf('%s %s %s', $contact->getCivility(), $contact->getFirstname(), $contact->getLastname()) : null),
			        "city" => $company->getCity(),
			        "rcs" => $company->getSiren(),
			        "has_phone" => !empty($company->getPhone()),
			        "has_website" => !empty($company->getWebsite()),
			        "has_info" => $hasInfo,
			        "has_bareme" => $hasFee
		        ]
	        ];

	        if( $hasFee && $company->getAcheterLouerId())
                $companies_with_fee[$company->getAcheterLouerId()] = $company->getId();

	        if( $hasInfo ){

		        $companies_with_infos[$company->getId()] = [
			        'phone'=>$this->encrypt($company->getPhone()),
			        'email'=>$this->encrypt($company->getEmail())
		        ];
	        }

	        $i++;
        }

        $outputDir = $_ENV['DIRECTORY_PATH'];

        $filesystem = new Filesystem();

        if(!$filesystem->exists($outputDir))
            $filesystem->mkdir($outputDir, 0755);

        file_put_contents(sprintf('%s/%s', $_ENV['DIRECTORY_PATH'], 'agencies.json'), json_encode($formatedData));
        file_put_contents(sprintf('%s/%s', $_ENV['DIRECTORY_PATH'], 'agencies_with_fees.json'), json_encode($companies_with_fee));
        file_put_contents(sprintf('%s/%s', $_ENV['DIRECTORY_PATH'], 'agencies_with_infos.json'), json_encode($companies_with_infos));

        $io->success('Les fichiers ont été exporté avec succès');
    }
}