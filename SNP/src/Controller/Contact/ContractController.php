<?php

namespace App\Controller\Contact;

use App\Entity\Contact;
use App\Entity\Registration;
use App\Repository\SignatoryRepository;
use App\Repository\SignatureRepository;
use App\Repository\UserRepository;
use App\Service\CaciService;
use App\Service\ContraliaAction;
use Exception;
use App\Service\EudonetAction;
use Doctrine\ORM\ORMException;
use App\Service\ServicesAction;
use Psr\Cache\InvalidArgumentException;
use Swagger\Annotations as SWG;
use App\Controller\AbstractController;
use App\Form\Type\ContactContractType;
use App\Repository\RegistrationRepository;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * Contact Contract Controller
 *
 * @SWG\Tag(name="Contact Contract")
 *
 * @Security(name="Authorization")
 * @IsGranted("ROLE_CONTACT")
 *
 */
class ContractController extends AbstractController
{
    /**
     * Add contracts
     *
     * @Route("/contact/contract", methods={"POST"})
     *
     * @SWG\Parameter( name="contact", in="body", required=true, description="Contract information", @SWG\Schema( type="object",
     *     @SWG\Property(property="rcp", type="boolean"),
     *     @SWG\Property(property="jp", type="boolean"),
     *     @SWG\Property(property="date", type="string")
     * ))
     * @SWG\Response(response=200, description="Updated contact")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param CaciService $caciService
     * @param Request $request
     * @param RegistrationRepository $registrationRepository
     * @param UserRepository $userRepository
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws ORMException
     */
    public function addContracts(CaciService $caciService, Request $request, RegistrationRepository $registrationRepository, UserRepository $userRepository)
    {
	    $user = $this->getUser();

	    if( !$user->isCommercialAgent() )
		    return $this->respondError('User is not a commercial agent');

	    $form = $this->submitForm(ContactContractType::class, $request);

	    if( !$form->isValid() )
		    return $this->respondBadRequest('Invalid arguments.', $this->getErrors($form));

	    $contact = $user->getContact();

        if( !$registration = $user->getRegistration() ){

            $registration = new Registration();
            $user->setRegistration($registration);

            $userRepository->save($user);
        }

        $caciService->addContracts($contact, $registration, $form, $request);

        $registration->setContract(true);
        $registrationRepository->save($registration);

	    return $this->respondOK();
    }

    /**
     * Get contract quote
     *
     * @Route("/contact/contract/quote", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Updated contact")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param CaciService $caciService
     * @return JsonResponse
     * @throws Exception|ExceptionInterface
     */
    public function getContractQuote(Request $request, CaciService $caciService)
    {
	    $user = $this->getUser();

	    if( !$user->isCommercialAgent() )
		    return $this->respondError('User is not a commercial agent');

	    set_time_limit(180);

	    $response = $caciService->insuranceQuote($request, $user);

	    return $this->respondOK($response);
    }

	/**
	 * Set contract agencies
	 *
	 * @Route("/contact/contract/agencies", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="Updated contact")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param RegistrationRepository $registrationRepository
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
    public function setContractAgencies(RegistrationRepository $registrationRepository)
    {
	    $user = $this->getUser();

	    if( $registration = $user->getRegistration() ){

		    $registration->setAgencies(true);
		    $registrationRepository->save($registration);
	    }

	    return $this->respondOK();
    }


    /**
     * Send signature Code
     *
     * @Route("/contact/contract/asseris", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Updated contact")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param ServicesAction $servicesAction
     * @param ContraliaAction $contraliaAction
     * @param SignatureRepository $signatureRepository
     * @param ParameterBagInterface $parameterBag
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function sendAsserisCode(ServicesAction $servicesAction, ContraliaAction $contraliaAction, SignatureRepository $signatureRepository, ParameterBagInterface $parameterBag)
    {
	    $user = $this->getUser();

	    if( !$user->isCommercialAgent() )
		    return $this->respondError('User is not a commercial agent');

	    $contact = $user->getContact();
		$address = $contact->getAddress();

        /** @var Registration $registration */
        $registration = $user->getRegistration();

        if( !$signature = $signatureRepository->findOneByEntity($contact, 'ASSERIS') ){

            $files = [];

            if( $registration->getContractRCP() ){

                $filePath = sprintf('%s/var/storage/registrations/%s/docs/bulletin/BULLETIN-RCP-unsigned.pdf', $parameterBag->get('kernel.project_dir'), $registration->getRegistrationFolderName());
                $files[$filePath] = ['name'=>'rcp', 'fields'=>['width'=>150, 'height'=>60, 'per_row'=>1, 'page'=>1, 'offset_x'=>10, 'offset_y'=>10, 'origin_x'=>15, 'origin_y'=>80]];
            }

            if( $registration->getContractPJ() ){

                $filePath = sprintf('%s/var/storage/registrations/%s/docs/bulletin/BULLETIN-PJ-unsigned.pdf', $parameterBag->get('kernel.project_dir'), $registration->getRegistrationFolderName());
                $files[$filePath] = ['name'=>'pj', 'fields'=>['width'=>150, 'height'=>60, 'per_row'=>1, 'page'=>1, 'offset_x'=>10, 'offset_y'=>10, 'origin_x'=>20, 'origin_y'=>80]];
            }


            $signature = $servicesAction->initiateSignatureCollect([$address], $contact, 'ASSERIS', $files);
        }

        $signatories = $signature->getSignatories();

        $params = [
            'customMessage'=> "Bonjour, Le code de sécurité pour la signature de votre bulletin de souscription ASSERIS est le : {OTP}.",
            'email'=> null,
            'deliveryMode'=> 'SMS'
        ];

        foreach ($signatories as $signatory )
            $contraliaAction->getOtp($signatory, $params);

	    return $this->respondOK(count($signatories));
    }

    /**
     * Check Asseris signature Code
     *
     * @Route("/contact/contract/asseris", methods={"POST"})
     *
     * @SWG\Parameter( name="contact", in="body", required=true, description="Code information", @SWG\Schema( type="object",
     *     @SWG\Property(property="code", type="string")
     * ))
     *
     * @SWG\Response(response=200, description="Updated contact")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param EudonetAction $eudonetAction
     * @param ParameterBagInterface $parameterBag
     * @param RegistrationRepository $registrationRepository
     * @param SignatoryRepository $signatoryRepository
     * @param ContraliaAction $contraliaAction
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws InvalidArgumentException
     */
    public function checkAsserisCode(Request $request, EudonetAction $eudonetAction, ParameterBagInterface $parameterBag, RegistrationRepository $registrationRepository, SignatoryRepository $signatoryRepository, ContraliaAction $contraliaAction)
    {
	    $user = $this->getUser();

	    if( !$user->isCommercialAgent() )
		    return $this->respondError('User is not a commercial agent');

	    $contact = $user->getContact();
        $address = $contact->getAddress();

        if( !$signatory = $signatoryRepository->findOneByEntity($contact, $address) )
            return $this->respondNotFound("Signatory not found");

        $signature = $signatory->getSignature();

        $contraliaAction->sign($signatory, $request->get('code'));
        $contraliaAction->terminate($signature);

        /** @var Registration $registration */
        if( $registration = $user->getRegistration() ){

            if( $contract = $registration->getContractRCP() ){

                $filepath = sprintf('%s/var/storage/registrations/%s/docs/bulletin/BULLETIN-RCP-signed.pdf', $parameterBag->get('kernel.project_dir'), $registration->getRegistrationFolderName());

                if( $contraliaAction->getFinalDoc($signature, 'rcp', $filepath) )
                    $eudonetAction->uploadFile('contract', $contract->getId(), $contact, null, $filepath, 'AC_SOUS_RCP_BULLETIN');

                $eudonetAction->pull($contract);
            }

            if( $contract = $registration->getContractPJ() ){

                $filepath = sprintf('%s/var/storage/registrations/%s/docs/bulletin/BULLETIN-PJ-signed.pdf', $parameterBag->get('kernel.project_dir'), $registration->getRegistrationFolderName());

                if( $contraliaAction->getFinalDoc($signature, 'pj', $filepath) )
                    $eudonetAction->uploadFile('contract', $contract->getId(), $contact, null, $filepath, 'AC_SOUS_PJ_BULLETIN');

                $eudonetAction->pull($contract);
            }

            $registration->setValidAsseris(true);
		    $registrationRepository->save($registration);
	    }

	    return $this->respondOK();
    }

    /**
     * Send signature Code
     *
     * @Route("/contact/contract/caci", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Updated contact")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param ServicesAction $servicesAction
     * @param ContraliaAction $contraliaAction
     * @param SignatureRepository $signatureRepository
     * @param ParameterBagInterface $parameterBag
     * @return JsonResponse
     *
     * @throws Exception
     */
    public function sendCaciCode(ServicesAction $servicesAction, ContraliaAction $contraliaAction, SignatureRepository $signatureRepository, ParameterBagInterface $parameterBag)
    {
        $user = $this->getUser();

        if( !$user->isCommercialAgent() )
            return $this->respondError('User is not a commercial agent');

        $contact = $user->getContact();
        $address = $contact->getAddress();

        if( !$signature = $signatureRepository->findOneByEntity($contact, 'SNPI') ){

            $filePath = sprintf('%s/var/storage/registrations/%s/docs/bulletin/BULLETIN-CACI-unsigned.pdf', $parameterBag->get('kernel.project_dir'), $user->getRegistration()->getRegistrationFolderName());
            $signature = $servicesAction->initiateSignatureCollect([$address], $contact, 'SNPI', [$filePath=>['name'=>'caci', 'fields'=>['width'=>150, 'height'=>60, 'per_row'=>1, 'page'=>1, 'offset_x'=>10, 'offset_y'=>10, 'origin_x'=>100, 'origin_y'=>230]]]);
        }

        $signatories = $signature->getSignatories();

        $params = [
            'customMessage'=> "Bonjour, Le code de sécurité pour la signature de votre bulletin d'adhésion au SNPI est le : {OTP}.",
            'email'=> null,
            'deliveryMode'=> 'SMS'
        ];

        foreach ($signatories as $signatory )
            $contraliaAction->getOtp($signatory, $params);

        return $this->respondOK();
    }

    /**
     * Check Caci signature Code
     *
     * @Route("/contact/contract/caci", methods={"POST"})
     *
     * @SWG\Parameter( name="contact", in="body", required=true, description="Code information", @SWG\Schema( type="object",
     *     @SWG\Property(property="code", type="string")
     * ))
     *
     * @SWG\Response(response=200, description="Updated contact")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param ParameterBagInterface $parameterBag
     * @param EudonetAction $eudonetAction
     * @param RegistrationRepository $registrationRepository
     * @param SignatoryRepository $signatoryRepository
     * @param ContraliaAction $contraliaAction
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws ORMException
     */
    public function checkCaciCode(Request $request, ParameterBagInterface $parameterBag, EudonetAction $eudonetAction, RegistrationRepository $registrationRepository, SignatoryRepository $signatoryRepository, ContraliaAction $contraliaAction)
    {
        $user = $this->getUser();

        if( !$user->isCommercialAgent() )
            return $this->respondError('User is not a commercial agent');

        /** @var Contact $contact */
        $contact = $user->getContact();
        $address = $contact->getAddress();

        if( !$signatory = $signatoryRepository->findOneByEntity($contact, $address) )
            return $this->respondNotFound("Signatory not found");

        $signature = $signatory->getSignature();

        $contraliaAction->sign($signatory, $request->get('code'));
        $contraliaAction->terminate($signature);

        /** @var Registration $registration */
        if( $registration = $user->getRegistration() ){

            if( $membershipId = $registration->getMembershipId() ){

                $filepath = sprintf('%s/var/storage/registrations/%s/docs/bulletin/BULLETIN-CACI-signed.pdf', $parameterBag->get('kernel.project_dir'), $user->getRegistration()->getRegistrationFolderName());

                if ( $contraliaAction->getFinalDoc($signature, 'caci', $filepath) )
                    $eudonetAction->uploadFile('membership', $membershipId, $contact, null, $filepath, 'AC_SOUS_SNPI_BULLETIN');
            }

            $registration->setValidCaci(true);
            $registrationRepository->save($registration);
        }

	    return $this->respondOK();
    }
}
