<?php

namespace App\Service;

use App\Repository\RegistrationRepository;
use DateTime;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Psr\Cache\InvalidArgumentException;
use SplFileInfo;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use ZipArchive;
use App\Entity\User;
use App\Entity\Address;
use App\Entity\Contact;
use App\Entity\Contract;
use App\Entity\Download;
use App\Entity\OrderDetail;
use App\Entity\Registration;
use Mpdf\Output\Destination;
use RecursiveIteratorIterator;
use App\Entity\ContractDetails;
use RecursiveDirectoryIterator;
use App\Repository\ContractRepository;
use App\Repository\DownloadRepository;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Environment as TwigEnvironment;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CaciService extends AbstractService
{
    private $eudonetConnector;
    private $eudonetAction;
    private $entityManager;
    private $parameterBag;
    private $twig;
    private $router;

    public function __construct(EudonetConnector $eudonetConnector, EudonetAction $eudonetAction, EntityManagerInterface $entityManager, ParameterBagInterface $parameterBag, TwigEnvironment $twig, RouterInterface $router){

        $this->eudonetConnector = $eudonetConnector;
        $this->eudonetAction = $eudonetAction;
        $this->entityManager = $entityManager;
        $this->parameterBag = $parameterBag;
        $this->twig = $twig;
        $this->router = $router;
    }

    /**
     * @param UserInterface $user
     * @return void
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
    public function saveQuote(UserInterface $user){

        $registration = $user->getRegistration();
        $filename = false;
        $contact = $user->getContact();

        if ( $registration->getContractPJ() && $contract = $registration->getContractRCP() )
            $filename = sprintf("AC_SOUS_RCP_PJ_DEVIS_PM0_PP%d.pdf", $contact->getId());
        elseif ( $contract = $registration->getContractRCP() )
            $filename = sprintf("AC_SOUS_PJ_DEVIS_PM0_PP%d.pdf", $contact->getId());
        elseif ( $contract = $registration->getContractPJ() )
            $filename = sprintf("AC_SOUS_RCP_DEVIS_PM0_PP%d.pdf", $contact->getId());

        //todo: not working
        if( $filename ){

            $folderPath = sprintf('%s/var/storage/registrations/%s/docs/devis', $this->parameterBag->get('kernel.project_dir'), $registration->getRegistrationFolderName());
            $filepath = $folderPath.'/'.$filename;

            if( file_exists($filepath) )
                $this->eudonetAction->uploadFile('contract', $contract->getId(), $contact, null, $filepath, $filename, false);
        }
    }

    public function addInscription(Registration $registration, array $registrationData)
    {
        $registration
            ->setHasAlreadyRcpAc($registrationData['has_already_rcp_ac'])
            ->setInsurerNameRcpAc($registrationData['insurer_name']);

        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $filesystem = new Filesystem();
        $foldersToCreate = [
            '/', '/temp', '/docs', '/docs/attestation', '/docs/bulletin', '/docs/devis',
            '/docs/facture','/docs/echeancier', '/docs/justif', 'docs/mandat'
        ];

        foreach ($foldersToCreate as $folder) {
            $folderPath = sprintf(
                '%s/var/storage/registrations/%s/%s',
                $this->parameterBag->get('kernel.project_dir'),
                $registration->getRegistrationFolderName(),
                $folder
            );

            if (! $filesystem->exists($folderPath))
                $filesystem->mkdir($folderPath);
        }

        return true;
    }

    /**
     * @throws InvalidArgumentException
     * @throws ExceptionInterface
     * @throws \Exception
     */
    public function insuranceCart(Registration $registration, Contact $contact, array $contractData)
    {
        /** @var RegistrationRepository $registrationRepository */
        $registrationRepository = $this->entityManager->getRepository(Registration::class);

        $insuranceEffectDate = DateTime::createFromFormat('d/m/Y', $contractData['field_date_effet_assurance']);
        $insuranceEffectYear = $insuranceEffectDate->format('Y');
	    $insuranceEffectEndDate = new DateTime('last day of December ' . $insuranceEffectYear);
	    $insuranceEffectMidYearDate = new DateTime('first day of July ' . $insuranceEffectYear);

        if ($contractData['field_assurance_rcp'] == 1 || $contractData['field_assurance_pj'] == 1) {

	        /** @var ContractRepository $contractRepository */
	        $contractRepository = $this->entityManager->getRepository(Contract::class);
	        $contracts = $contractRepository->findBy(['contact'=>$contact, 'status'=>'pending', 'category'=>['PJ CACI', 'RCP CACI']]);

			foreach ($contracts as $contract){

				$contract->setStatus('abandoned');
				$this->eudonetAction->push($contract);
			}
        }

        /****************************************************************************/
        /***** On récupère la société sélectionnée en agence principale *****/
        /****************************************************************************/

        if (! $address = $contact->getWorkAddress() )
            throw new \Exception("Address not found");

        if ( !$company = $address->getCompany() )
            throw new \Exception("Company not found");

        $registration->setContractRCP(null);
        $registration->setContractPJ(null);

        $registrationRepository->save($registration);

        /**********************************************************/
        /***** On regarde si le contrat RCP a été sélectionné *****/
        /**********************************************************/

        if ($contractData['field_assurance_rcp']) {

            // Insertion du contrat
            $rcpContract = new Contract();

            $rcpContract
                ->setEntity('asseris')
                ->setStatus('pending')
                ->setCategory('RCP CACI')
                ->setContact($contact)
                ->setInsurer('serenis')
                ->setnonRenewable(false)
                ->setPolicyNumber('')
                ->setPaymentMethod('credit_card')
                ->setWeb(true)
                ->setStartDate($insuranceEffectDate)
                ->setEndDate($insuranceEffectEndDate)
                ->setCompany($company);

	        $this->eudonetAction->push($rcpContract);

            $registration->setContractRCP($rcpContract);
            $registrationRepository->save($registration);

            // On ajoute une ligne de détail contrat -> garantie RCP

            $rcpContractDetailsProductPrime = new ContractDetails();

            $rcpContractDetailsProductPrime
                ->setProduct(ContractDetails::RCP_PRODUCT_PRIME_FILEID)
                ->setQuantity(1)
                ->setNonRenewable(false)
                ->setContact($contact)
                ->setContract($rcpContract);

            if ($insuranceEffectDate->getTimestamp() >= $insuranceEffectMidYearDate->getTimestamp() )
                $rcpContractDetailsProductPrime->setProrata('1002661');

            $this->eudonetAction->push($rcpContractDetailsProductPrime);

            // On ajoute une ligne de détail contrat -> frais de création RCP

            $rcpContractDetailsProductApplicationFee = new ContractDetails();

            $rcpContractDetailsProductApplicationFee
                ->setProduct(ContractDetails::RCP_PRODUCT_APPLICATION_FEE_FILEID)
                ->setQuantity(1)
                ->setNonRenewable(true)
                ->setContact($contact)
                ->setContract($rcpContract);

            $this->eudonetAction->push($rcpContractDetailsProductApplicationFee);
        }

        /*********************************************************/
        /***** On regarde si le contrat PJ a été sélectionné *****/
        /*********************************************************/

        if ($contractData['field_assurance_pj'] == 1) {
            // Insertion du contrat
            $pjContract = new Contract();

            $pjContract
                ->setEntity('asseris')
                ->setStatus('pending')
                ->setCategory('PJ CACI')
                ->setContact($contact)
                ->setInsurer('groupama')
                ->setnonRenewable(false)
                ->setPolicyNumber('')
                ->setPaymentMethod('credit_card')
                ->setWeb(true)
                ->setStartDate($insuranceEffectDate)
                ->setEndDate($insuranceEffectEndDate)
                ->setCompany($company);

            $this->eudonetAction->push($pjContract);

            $registration->setContractPJ($pjContract);
            $registrationRepository->save($registration);

            // On ajoute une ligne de détail contrat -> garantie PJ

            $pjContractDetailsProductPrime = new ContractDetails();

            $pjContractDetailsProductPrime
                ->setProduct(ContractDetails::PJ_PRODUCT_PRIME_FILEID)
                ->setQuantity(1)
                ->setNonRenewable(false)
                ->setContact($contact)
                ->setContract($pjContract);

            if ($insuranceEffectDate->getTimestamp() >= $insuranceEffectMidYearDate->getTimestamp() )
                $pjContractDetailsProductPrime->setProrata('1002661');

            $this->eudonetAction->push($pjContractDetailsProductPrime);

            // On ajoute une ligne de détail contrat -> frais de création RCP

            $pjContractDetailsProductApplicationFee = new ContractDetails();

            $pjContractDetailsProductApplicationFee
                ->setProduct(ContractDetails::PJ_PRODUCT_APPLICATION_FEE_FILEID)
                ->setQuantity(1)
                ->setNonRenewable(false)
                ->setContact($contact)
                ->setContract($pjContract);

            $this->eudonetAction->push($pjContractDetailsProductApplicationFee);
        }

        /*******************************************************************************************************************************/
        /***** On ajoute une cotisation SNPI GRATUITE s'il n'en a pas déjà une pour l'année en cours en statut "ne pas renouveler" *****/
        /*******************************************************************************************************************************/

        $currentYear = date('Y');
        $currentYearValue = false;

        foreach ($this->eudonetConnector->catalog('10108') as $catalogValue) {

            if ($catalogValue['DisplayValue'] == $currentYear)
                $currentYearValue = $catalogValue['DBValue'];
        }

        $membershipResult = $this->eudonetConnector->execute(
            $this->eudonetConnector->createQueryBuilder()
                ->select(['label', 'status'])
                ->from('membership')
                ->where('contact_id', '=', $contact->getId())
                ->andWhere('year', '=', $currentYearValue)
                ->andWhere('do_not_renew', '=', '0') // Ne pas renouveler => décoché
        );

        if (! $membershipResult) {

            // delete cotisations documents
            $filesystem = new Filesystem();

            $dirPath = sprintf(
                '%s/var/storage/registrations/%s',
                $this->parameterBag->get('kernel.project_dir'),
                $registration->getRegistrationFolderName()
            );

            if ($filesystem->exists($dirPath)) {

                $foldersToDelete = ['/docs/attestation', '/docs/bulletin', '/docs/devis', '/docs/facture'];

                foreach ($foldersToDelete as $folder)
                    $filesystem->remove($folder);
            }

            $membershipId = $this->eudonetConnector->insert('membership', [
                'year' => $currentYearValue,
                'contact_id' => $contact->getId(),
                'status' => 'in_progress',
                'price_type' => 162,
                'type' => 3231,
                'membership_date' => date("Y/m/d") . " 00:00:00",
                'do_not_renew' => false,
                'web' => '1'
            ]);

            if ( !$membershipId )
                throw new \Exception("Membership insertion error");

            $registration->setMembershipId($membershipId);
            $registrationRepository->save($registration);

            $membershipDetailResult = $this->eudonetConnector->execute(
                $this->eudonetConnector->createQueryBuilder()
                    ->select('number')
                    ->from('membership_details')
                    ->where('membership_id', '=', $membershipId)
                    ->andWhere('product', '=', '162')
                    ->orderBy('number', 'DESC')
            );

            if ( !$membershipDetailResult )
                throw new \Exception("Membership details search error");

            $membershipDetailId = stripslashes($membershipDetailResult[0]["id"]);

            $currentMonth = date("m");
            $prorata = '';
            $prorataKey = 12 - intval($currentMonth);

            for ($i = 1; $i <= 12; $i++) {
                if ($prorataKey == $i)
                    $prorata = (string) (1002656 + $i - 1);
            }

            $this->eudonetConnector->update('membership_details', $membershipDetailId, ['prorata' => $prorata]);
            $this->eudonetConnector->update('membership', $membershipId, ['generate_invoice' => true]);
            $this->eudonetConnector->update('membership_details', $membershipDetailId, ['validate' => true]);
        }
    }


	/**
	 * @param Request $request
	 * @param User $user
	 * @return array
	 * @throws ExceptionInterface
	 * @throws \Doctrine\ORM\ORMException
	 * @throws \Mpdf\MpdfException
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 */
    public function insuranceQuote(Request $request, UserInterface $user)
    {
		$contact = $user->getContact();
		$registration = $user->getRegistration();
	    $filesystem = new Filesystem();

        $folderPath = sprintf('%s/var/storage/registrations/%s/docs/devis', $this->parameterBag->get('kernel.project_dir'), $registration->getRegistrationFolderName());

        if (! $filesystem->exists($folderPath))
            $filesystem->mkdir($folderPath);
        else if (! is_writable($folderPath))
            $filesystem->chmod($folderPath, 0777);

        /** @var ContractRepository $contractRepository */
        $contractRepository = $this->entityManager->getRepository(Contract::class);

        $contracts = $contractRepository->findBy(['contact'=>$contact, 'status'=>'pending']);

        if( !count($contracts) )
            throw new NotFoundHttpException('Contract not found');

        $isRcpContract = false;
        $isPjContract = false;

	    foreach ($contracts as $contract) {
		    if ($contract->getCategory() =='PJ CACI')
			    $isPjContract = true;
		    else if ($contract->getCategory() == 'RCP CACI')
			    $isRcpContract = true;
	    }

	    $status = $this->eudonetAction->getMembershipStatus($user, 'caci');

        // generate caci quotation
        $html = $this->twig->render('pdf/quotations/caci-quotation.html.twig', [
            'contact' => $contact,
            'startDate' => $contracts[0]->getStartDate()
        ]);

        $caciQuotationFilepath = sprintf('%s/AC_SOUS_SNPI_DEVIS_PM0_PP%d.pdf', $folderPath, $contact->getId());

        $mpdf = new Mpdf(['tempDir' => dirname(__DIR__, 2) . '/var/tmp']);
        $mpdf->WriteHTML($html);
        $mpdf->Output($caciQuotationFilepath, Destination::FILE);

        // TODO: insert caci quotation file to eudonet

        // generate rcp pj quotation
        if ($isRcpContract && $isPjContract)
            $contractType = 'rcp-pj';
        elseif ($isRcpContract)
            $contractType = 'rcp';
        elseif ($isPjContract)
            $contractType = 'pj';
        else
            throw new \Exception("Contract type not found");

        $totalAmount = $status['total_amount'];

        $html = $this->twig->render('pdf/quotations/rcp-pj-quotation.html.twig', [
            'contact' => $contact,
            'startDate' => $contracts[0]->getStartDate(),
            'contractType' => $contractType,
            'totalAmount' => $totalAmount
        ]);

        $filename = null;

        if ( $contractType == 'rcp-pj' )
            $filename = sprintf("AC_SOUS_RCP_PJ_DEVIS_PM0_PP%d", $contact->getId());
        elseif ( $contractType == 'rcp' )
            $filename = sprintf("AC_SOUS_PJ_DEVIS_PM0_PP%d", $contact->getId());
        elseif ( $contractType == 'pj' )
            $filename = sprintf("AC_SOUS_RCP_DEVIS_PM0_PP%d", $contact->getId());

        $rcpPjQuotationFilepath = sprintf('%s/%s.pdf', $folderPath, $filename);

        $mpdf = new Mpdf(['tempDir' => dirname(__DIR__, 2) . '/var/tmp']);
        $mpdf->WriteHTML($html);
        $mpdf->Output($rcpPjQuotationFilepath,Destination::FILE);

        // compress files
        $tempZipFolder =  sprintf('%s/var/tmp/zip/%s', $this->parameterBag->get('kernel.project_dir'), $registration->getRegistrationFolderName());

        if (! $filesystem->exists($tempZipFolder))
            $filesystem->mkdir($tempZipFolder);

        $filesystem->copy($caciQuotationFilepath, $tempZipFolder . '/Devis-CACI.pdf');
        $filesystem->copy($rcpPjQuotationFilepath, $tempZipFolder . '/Devis-'.strtoupper($contractType).'.pdf');

        $cgFolderPath = sprintf('%s/templates/files', $this->parameterBag->get('kernel.project_dir'));

        if ( $contractType == 'rcp-pj' || $contractType == 'pj' )
            $filesystem->copy($cgFolderPath . '/CG-Protection-Juridique.pdf', $tempZipFolder . '/CG-Protection-Juridique.pdf');

        if ( $contractType == 'rcp-pj' || $contractType == 'rcp' )
            $filesystem->copy($cgFolderPath . '/CG_Responsabilite-Civile-Professionnelle.pdf', $tempZipFolder . '/CG_Responsabilite-Civile-Professionnelle.pdf');

        // Initialize archive object
        $zip = new ZipArchive();
        $zipFilePath = sprintf('%s/var/storage/registrations/%s/docs/devis/CACI-Devis-CG.zip', $this->parameterBag->get('kernel.project_dir'), $registration->getRegistrationFolderName());

        $zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempZipFolder), RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($files as $file) {

            if (! $file->isDir()) {

                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($tempZipFolder) + 1);

                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        $filesystem->remove($tempZipFolder);

        /** @var DownloadRepository */
        $downloadRepository = $this->entityManager->getRepository(Download::class);

        /** @var Download */
        $download = $downloadRepository->create($request, $zipFilePath);

        return [
            'date_begin_contract' => $contracts[0]->getStartDate(),
            'date_end_contract' => $contracts[0]->getEndDate(),
            'quote' => $this->router->generate('download_attachment', ['uuid' => $download->getUuid(), 'filename' => 'CACI-Devis-CG.zip'], UrlGeneratorInterface::ABSOLUTE_URL),
            'signin_amount' => 0,
            'insurance_amount' => $status['total_amount'],
            'insurance_fees' => $status['total_fees']
        ];
    }

    /**
     * @param UserInterface $user
     * @param OrderDetail $orderDetail
     * @param string $folderPath
     * @return void
     * @throws \Mpdf\MpdfException
     * @throws InvalidArgumentException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generateMembershipRcpBulletin(UserInterface $user, OrderDetail $orderDetail, string $folderPath)
    {
        /** @var ContractRepository $contractRepository */
        $contractRepository = $this->entityManager->getRepository(Contract::class);

        /** @var Contact $contact */
        $contact = $user->getContact();
        $address = $contact->getWorkAddress();

        $registration = $user->getRegistration();

        $contract = $contractRepository->findOneBy(['contact' => $contact, 'category' => 'RCP CACI']);

        $this->eudonetAction->pull($contract);

        $productResult = $this->eudonetConnector->select(['selling_price'], 'product', ContractDetails::RCP_PRODUCT_PRIME_FILEID);

        $fees = 0;

        $qb = $this->eudonetConnector->createQueryBuilder();
        $qb->select(['price','product'])->from('contract_details')->where('contract','=', $contract->getId());

        if( $details = $this->eudonetConnector->execute($qb) ){

            foreach ($details as $detail){

                if( $detail['product'] == '135')
                    $fees += $this->formatFloat($detail['price']);
            }
        }

        $sellingPrice = $productResult ? $this->parseFloat($productResult['selling_price']) : 0;

        $html = $this->twig->render('pdf/bulletins/rcp-bulletin.html.twig', [
            'contact' => $contact,
            'address' => $address,
            'contract' => $contract,
            'registration' => $registration,
            'orderDetail' => $orderDetail,
            'fees' => $fees,
            'sellingPrice' => $sellingPrice
        ]);

        $rcpBulletinPath = sprintf(
            '%s/BULLETIN-RCP-unsigned.pdf',
            $folderPath
        );

        $mpdf = new Mpdf(['tempDir' => dirname(__DIR__, 2) . '/var/tmp']);
        $mpdf->WriteHTML($html);
        $mpdf->Output($rcpBulletinPath,Destination::FILE);
    }

    /**
     * @param UserInterface $user
     * @param OrderDetail $orderDetail
     * @param string $folderPath
     * @return void
     * @throws \Mpdf\MpdfException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generateMembershipPjBulletin(UserInterface $user, OrderDetail $orderDetail, string $folderPath)
    {
        /** @var ContractRepository $contractRepository */
        $contractRepository = $this->entityManager->getRepository(Contract::class);

        $contact = $user->getContact();
        $contract = $contractRepository->findOneBy(['contact' => $contact, 'category' =>'PJ CACI']);

        $this->eudonetAction->pull($contract);

        $html = $this->twig->render('pdf/bulletins/pj-bulletin.html.twig', [
            'contact' => $contact,
            'contract' => $contract,
            'orderDetail' => $orderDetail
        ]);

        $pjBulletinPath = sprintf(
            '%s/BULLETIN-PJ-unsigned.pdf',
            $folderPath
        );

        $mpdf = new Mpdf(['tempDir' => dirname(__DIR__, 2) . '/var/tmp']);
        $mpdf->WriteHTML($html);
        $mpdf->Output(
            $pjBulletinPath,
            Destination::FILE
        );
    }

    /**
     * @param UserInterface $user
     * @return void
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws MpdfException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generateMembershipCaciBulletin(UserInterface $user)
    {
        $membershipQueryResult = $this->eudonetConnector->execute(
            $this->eudonetConnector->createQueryBuilder()
                ->select('membership_date')
                ->from('membership')
                ->where('contact_id', '=', $user->getContact()->getId())
        );

        $folderPath = sprintf(
            '%s/var/storage/registrations/%s/docs/bulletin',
            $this->parameterBag->get('kernel.project_dir'),
            $user->getRegistration()->getRegistrationFolderName()
        );

        if ( !$membershipQueryResult )
            return;

        $membershipDate = DateTime::createFromFormat('Y/m/d H:i:s', $membershipQueryResult[0]['membership_date']);

        $html = $this->twig->render('pdf/bulletins/caci-bulletin.html.twig', [
            'contact' => $user->getContact(),
            'membershipDate' => $membershipDate
        ]);

        $caciBulletinPath = sprintf(
            '%s/BULLETIN-CACI-unsigned.pdf',
            $folderPath
        );

        $mpdf = new Mpdf(['tempDir' => dirname(__DIR__, 2) . '/var/tmp']);
        $mpdf->WriteHTML($html);
        $mpdf->Output(
            $caciBulletinPath,
            Destination::FILE
        );
    }

    /**
     * @param Contact $contact
     * @param Contract $contract
     * @param string $folderPath
     * @return void
     * @throws \Mpdf\MpdfException
     * @throws InvalidArgumentException
     * @throws ExceptionInterface
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generatePjAttestation(Contact $contact, Contract $contract, string $folderPath)
    {
        $html = $this->twig->render('pdf/certificates/pj-certificate.html.twig', [
            'contact' => $contact,
            'contract' => $contract
        ]);

        $t = microtime(true);
        $micro = sprintf("%06d",($t - floor($t)) * 1000000);
        $d = new DateTime(date('y-m-d H:i:s.'.$micro,$t));
        $reference = substr($d->format("ymdHisu"), 0, 14);

        $pjAttestationPath = sprintf(
            '%s/ATTESTATION-PJ-%s-%s-%s.pdf',
            $folderPath,
            $contract->getStartDate()->format('Y'),
            $contact->getMemberId(),
            $reference
        );

        $mpdf = new Mpdf(['tempDir' => dirname(__DIR__, 2) . '/var/tmp']);
        $mpdf->WriteHTML($html);
        $mpdf->Output(
            $pjAttestationPath,
            Destination::FILE
        );

        $this->eudonetAction->uploadFile('contract', $contract->getId(), $contact, null, $pjAttestationPath, 'AC_SOUS_PJ_ATTESTATION');
    }

    /**
     * @param Contact $contact
     * @param Contract $contract
     * @param Address $address
     * @param string $folderPath
     * @return void
     * @throws \Mpdf\MpdfException
     * @throws InvalidArgumentException
     * @throws ExceptionInterface
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Exception
     */
    public function generateRcpAttestation(Contact $contact, Contract $contract, Address $address, string $folderPath)
    {
        if (! $company = $address->getCompany())
            throw new \Exception("Company not found");

        $html = $this->twig->render('pdf/certificates/rcp-certificate.html.twig', [
            'contact' => $contact,
            'contract' => $contract,
            'address' => $address,
            'company' => $company
        ]);

        $t = microtime(true);
        $micro = sprintf("%06d",($t - floor($t)) * 1000000);
        $d = new DateTime(date('y-m-d H:i:s.'.$micro,$t));
        $reference = substr($d->format("ymdHisu"), 0, 14);

        $rcpAttestationPath = sprintf(
            '%s/ATTESTATION-RCP-%s-%s-%s.pdf',
            $folderPath,
            $contract->getStartDate()->format('Y'),
            $contact->getMemberId(),
            $reference
        );

        $mpdf = new Mpdf(['tempDir' => dirname(__DIR__, 2) . '/var/tmp']);
        $mpdf->WriteHTML($html);
        $mpdf->Output(
            $rcpAttestationPath,
            Destination::FILE
        );

        $this->eudonetAction->uploadFile('contract', $contract->getId(), $contact, $company, $rcpAttestationPath, 'AC_SOUS_RCP_ATTESTATION');
    }

    /**
     * @param UserInterface $user
     * @param OrderDetail $orderDetail
     * @return void
     * @throws \Mpdf\MpdfException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function generateMembershipBulletin(UserInterface $user, OrderDetail $orderDetail)
    {
        $filesystem = new Filesystem();

        $folderPath = sprintf(
            '%s/var/storage/registrations/%s/docs/bulletin',
            $this->parameterBag->get('kernel.project_dir'),
            $user->getRegistration()->getRegistrationFolderName()
        );

        if (! $filesystem->exists($folderPath))
            $filesystem->mkdir($folderPath);
        else if (! is_writable($folderPath))
            $filesystem->chmod($folderPath, 0777);

        if ($orderDetail->getTitle() == 'RCP CACI')
            $this->generateMembershipRcpBulletin($user, $orderDetail, $folderPath);
        else if ($orderDetail->getTitle() == 'PJ CACI')
            $this->generateMembershipPjBulletin($user, $orderDetail, $folderPath);
    }


    /**
     * @param User $user
     * @param Contract $contract
     * @param ?Address $address
     *
     * @throws MpdfException
     * @throws InvalidArgumentException
     * @throws ExceptionInterface
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generateCertificate(User $user, Contract $contract, ?Address $address=null)
    {
        $filesystem = new Filesystem();

        $folderPath = sprintf('%s/var/storage/registrations/%s/docs/attestation', $this->parameterBag->get('kernel.project_dir'), $user->getRegistration()->getRegistrationFolderName());

        if (! $filesystem->exists($folderPath))
            $filesystem->mkdir($folderPath);
        else if (! is_writable($folderPath))
            $filesystem->chmod($folderPath, 0777);

        if( $contract->getCategory() =='PJ CACI' )
            $this->generatePjAttestation($user->getContact(), $contract, $folderPath);
        elseif( $contract->getCategory() == 'RCP CACI' )
            $this->generateRcpAttestation($user->getContact(), $contract, $address, $folderPath);
    }


    /**
     * @param Contact $contact
     * @param Registration $registration
     * @param FormInterface $form
     * @param Request $request
     * @return void
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     */
    public function addContracts(Contact $contact, Registration $registration, FormInterface $form, Request $request){

        set_time_limit(180);

        $criteria = $form->getData();

        /** @var DateTime $date */
        $date = $criteria['date'];

        if( !$date )
            $date = new DateTime();

        if( $contact->isMember() )
            $this->register($registration, $request);

        $this->insuranceCart($registration, $contact, [
            'field_assurance_rcp' => $criteria['rcp'] ? 1 : 0,
            'field_assurance_pj' => $criteria['pj'] ? 1 : 0,
            'field_date_effet_assurance' => $date->format('d/m/Y')
        ]);
    }


    /**
     * @param Registration $registration
     * @param Request $request
     * @return mixed
     */
    public function register(Registration $registration, Request $request){

        $hasRcpValue = 'no';
        $insurerName = "";

        if (strip_tags($request->get('hasRcp', 'non')) != "non") {
            if ($request->get('rcp', 'non') == "asseris") {
                $hasRcpValue = "asseris";
            } else {
                $hasRcpValue = "other";
                $insurerName = addslashes(trim(mb_strtoupper(strip_tags($request->get('insurer')))));
            }
        }

        return $this->addInscription($registration, [
            "has_already_rcp_ac" => $hasRcpValue,
            'insurer_name' => $insurerName,
            "step_ac" => 2,
            "status_ac" => true
        ]);
    }
}
