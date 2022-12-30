<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Company;
use App\Entity\CompanyBusinessCard;
use App\Entity\CompanyRepresentative;
use App\Entity\Contact;
use App\Entity\User;
use App\Form\Type\AddressType;
use App\Form\Type\BusinessCardType;
use App\Form\Type\CompanyCreateType;
use App\Form\Type\CompanyFeeType;
use App\Form\Type\CompanySearchType;
use App\Form\Type\CompanyUpdateType;
use App\Form\Type\ContactCreateType;
use App\Repository\AddressRepository;
use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Repository\EudoEntityMetadataRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\EudonetAction;
use App\Service\EudonetConnector;
use App\Service\Mailer;
use App\Service\SnpiConnector;
use App\Service\ValueChecker;
use Doctrine\ORM\ORMException;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Company Controller
 *
 * @SWG\Tag(name="Company")
 *
 * @Security(name="Authorization")
 */
class CompanyController extends AbstractController
{
	/**
	 * Check company data
	 *
	 * @IsGranted("ROLE_COMPANY")
	 * @IsGranted("ROLE_MEMBER")
	 *
	 * @Route("/company/checkup", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Returns an invalid data")
	 * @SWG\Response(response=404, description="Not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param UserRepository $userRepository
	 * @param ValueChecker $valueChecker
	 * @param ContactRepository $contactRepository
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 */
	public function checkup(UserRepository $userRepository, ValueChecker $valueChecker, ContactRepository  $contactRepository)
	{
		$user = $this->getUser();
		$count = 0;

		// Check Company Data

		/** @var Company $company */
		$company = $user->getCompany();

		$valueChecker
			->isValid('website', $company->getWebsite(), ['isEmpty'])
			->isValid('email', $company->getEmail(), ['isEmpty'])
			->isValid('phone', $company->getPhone(), ['isEmpty', 'isValidPhone']);

		$invalidCompanyData = $valueChecker->getErrors();
		$count += count($invalidCompanyData);

		// Check Collaborators Data

		/** @var User[] $collaborators */
		$collaborators = $userRepository->findBy([
			'company' => $company,
			'type' => 'collaborator'
		]);

		$invalidCollaborators = [];

		foreach ($collaborators as $collaborator) {

			$contact = $collaborator->getContact();
            $invalidData = [];

			if( $workAddress = $contact->getAddress($company) ){

                $valueChecker
                    ->isValid('email', $workAddress->getEmail(), ['isEmpty', 'isValidEmail'])
                    ->isValid('phone', $workAddress->getPhone(), ['isEmpty', 'isValidPhone'])
                    ->isValid('issuedAt', $workAddress->getIssuedAt(), ['isEmpty', 'isValidDate'])
                    ->isValid('expireAt', $workAddress->getExpireAt(), ['isEmpty', 'isValidDate']);
            }
			else{

                $valueChecker->addError('Work address is empty');
            }

			$invalidData['workAddress'] = $valueChecker->getErrors();
			$count += count($invalidData['workAddress']);

			if( $homeAddress = $contact->getAddress() ){

                $valueChecker
                    ->isValid('street1', $homeAddress->getStreet1(), ['isEmpty'])
                    ->isValid('zip', $homeAddress->getZip(), ['isEmpty'])
                    ->isValid('city', $homeAddress->getCity(), ['isEmpty']);
            }
            else{

                $valueChecker->addError('Home address is empty');
            }

			$invalidData['homeAddress'] = $valueChecker->getErrors();
			$count += count($invalidData['homeAddress']);

			$valueChecker
				->isValid('firstname', $contact->getFirstname(), ['isEmpty'])
				->isValid('lastname', $contact->getLastname(), ['isEmpty']);

			$invalidData['contact'] = $valueChecker->getErrors();
			$count += count($invalidData['contact']);

			if($invalidData['workAddress'] || $invalidData['homeAddress'] || $invalidData['contact']) {

				$invalidCollaborators[] = [
					'entity' => $contactRepository->hydrate($contact),
					'invalidData' => $invalidData
				];
			}
		}

		// Check LegalRepresentatives Data

		$legalRepresentativesContact = $company->getLegalRepresentatives();

		$invalidLegalRepresentatives = [];

		foreach ($legalRepresentativesContact as $legalRepresentativeContact) {

			$workAddress = $legalRepresentativeContact->getAddress($company);

			$invalidData = [];

			$valueChecker
				->isValid("email", $workAddress->getEmail(), ['isEmpty', 'isValidEmail'])
				->isValid("phone", $workAddress->getPhone(), ['isEmpty', 'isValidPhone'])
				->isValid("startedAt", $workAddress->getExpireAt(), ['isEmpty', 'isValidDate']);

			$invalidData['workAddress'] = $valueChecker->getErrors();
			$count += count($invalidData['workAddress']);

			$homeAddress = $legalRepresentativeContact->getAddress();

			$valueChecker
				->isValid("street1", $homeAddress->getStreet1(), ['isEmpty'])
				->isValid("zip", $homeAddress->getZip(), ['isEmpty'])
				->isValid("city", $homeAddress->getCity(), ['isEmpty']);

			$invalidData['homeAddress'] = $valueChecker->getErrors();
			$count += count($invalidData['homeAddress']);

			$valueChecker
				->isValid('firstname', $legalRepresentativeContact->getFirstname(), ['isEmpty'])
				->isValid('lastname', $legalRepresentativeContact->getLastname(), ['isEmpty']);

			$invalidData['contact'] = $valueChecker->getErrors();
			$count += count($invalidData['contact']);

			if($invalidData['workAddress'] || $invalidData['homeAddress'] || $invalidData['contact']) {

				$invalidLegalRepresentatives[] = [
					'entity' => $contactRepository->hydrate($legalRepresentativeContact),
					'invalidData' => $invalidData
				];
			}
		}

		return $this->respondOK([
			'count' => $count,
			'company' => $invalidCompanyData,
			'collaborators' => $invalidCollaborators,
			'legalRepresentatives' => $invalidLegalRepresentatives
		]);
	}


	/**
	 * Create company
	 *
	 * @IsGranted("ROLE_CONTACT")
	 *
	 * @Route("/company", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="Returns a company")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @SWG\Parameter( name="company", in="body", required=true, description="Company information", @SWG\Schema( type="object",
	 *     @SWG\Property(property="siren", type="string"),
	 *     @SWG\Property(property="name", type="string"),
	 *     @SWG\Property(property="street", type="string"),
	 *     @SWG\Property(property="zip", type="string"),
	 *     @SWG\Property(property="city", type="string"),
	 *     @SWG\Property(property="firstname", type="string"),
	 *     @SWG\Property(property="lastname", type="string"),
	 *     @SWG\Property(property="civility", type="string", enum={"Monsieur", "Madame"}),
	 *     @SWG\Property(property="addresses[0][position]", type="string"),
	 *     @SWG\Property(property="addresses[0][email]", type="string"),
	 *     @SWG\Property(property="kind[]", type="string"),
	 *     @SWG\Property(property="number", type="string"),
	 *     @SWG\Property(property="issuedAt", type="string"),
	 *     @SWG\Property(property="cci", type="integer"),
	 * ))
	 *
	 * @param Request $request
	 * @param CompanyRepository $companyRepository
	 * @param EudonetAction $eudonet
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws Exception
	 */
	public function create(Request $request, CompanyRepository $companyRepository, EudonetAction $eudonet)
	{
		// form validation
		$company = new Company();
		$companyForm = $this->submitForm(CompanyCreateType::class, $request, $company);

		if( !$companyForm->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($companyForm));

		$businessCard = new CompanyBusinessCard();
		$businessCardForm = $this->submitForm(BusinessCardType::class, $request, $businessCard);

		if( !$businessCardForm->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($businessCardForm));

		$contact = new Contact();
		$contactForm = $this->submitForm(ContactCreateType::class, $request, $contact);

		if( !$contactForm->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($contactForm));

		// extract address
		$address = clone $contact->getAddresses()[0];
		$contact->removeAddresses();

		// store company
		$company->setBrand($company->getName());
		$eudonet->push($company);

		// store contact
		$eudonet->push($contact);

		// update address
		$address->setIsActive(true);
		$address->setIsMain(true);
		$address->setCompany($company);
		$address->setContact($contact);
		$address->setPosition('Salariés et assimilés');
		$eudonet->push($address);

		// store representative
		$legalRepresentative = new CompanyRepresentative();
		$legalRepresentative->setContact($contact);
		$legalRepresentative->setCompany($company);
		$eudonet->push($legalRepresentative);

		// store business card
		$businessCard->setIsActive(true);
		$businessCard->setCompany($company);
		$businessCard->setExpireAt($businessCard->getIssuedAt()->modify('+3 years'));
		$eudonet->push($businessCard);

		return $this->respondCreated($companyRepository->hydrate($company));
	}


    /**
     * Get current company details
     *
     * @IsGranted("ROLE_CLIENT")
     *
     * @Route("/company", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Return company details")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=404, description="User not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param CompanyRepository $companyRepository
     * @param SnpiConnector $snpiConnector
     * @param RoleRepository $roleRepository
     * @param EudoEntityMetadataRepository $entityMetadataRepository
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
	public function find(CompanyRepository $companyRepository, SnpiConnector $snpiConnector, RoleRepository $roleRepository, EudoEntityMetadataRepository $entityMetadataRepository, Request $request)
	{
		$user = $this->getUser();

		if( !$company = $user->getCompany() )
			return $this->respondNotFound('Company not found');

		$data = $companyRepository->hydrate($company, $companyRepository::$HYDRATE_FULL);

        $data['roles'] = [];

        if( $companyMetadata = $entityMetadataRepository->findByEntity($company) ){

            if( $roles = $companyMetadata->getData('roles') ){

                foreach ($roles as $group=>&$group_roles)
                    $group_roles = $roleRepository->findRolesNameById($group_roles);

                $data['roles'] = $roles;
            }
        }

		if( $request->query->has('realEstate') )
			$data['realEstate'] = $snpiConnector->getRealEstateData($company);

		return $this->respondOK($data);
	}


	/**
	 * Save company fee guide
	 *
	 * @IsGranted("ROLE_COMPANY")
	 * @IsGranted("ROLE_MEMBER")
	 *
	 * @Route("/company/fee", methods={"POST"})
	 *
	 * @SWG\Response(response=200, description="Return ok")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @return JsonResponse
	 */
	public function postFee(Request $request)
	{
		$user = $this->getUser();
		$company = $user->getCompany();

		$feeForm = $this->submitForm(CompanyFeeType::class, $request);
		if( !$feeForm->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($feeForm));

		if( $file = $feeForm->get('file')->getData() )
			$this->moveUploadedFile($file, 'fee_directory', $company->getId().'.pdf');

		return $this->respondOK();
	}


	/**
	 * Download company fee guide
	 *
	 * @Route("/company/{id}/fee", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Return fee download url")
	 * @SWG\Response(response=404, description="Company not found")
	 *
	 * @param CompanyRepository $companyRepository
	 * @param $id
	 * @return Response
	 */
	public function downloadFee(CompanyRepository $companyRepository, $id)
	{
		if( !$company = $companyRepository->find($id) )
			return $this->respondNotFound('Company not found');

		$file = $this->getPath('fee_directory').'/'.$id.'.pdf';

		if( !file_exists($file) )
			$this->respondNotFound();

		$filename = 'bareme-d-honoraires-'.date('Y', filemtime($file)).'.pdf';

		return $this->respondFile($file, $filename, 'attachment');
	}


	/**
	 * Find company
	 *
	 * @IsGranted("ROLE_USER")
	 *
	 * @Route("/company/search", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Return company details")
	 * @SWG\Response(response=404, description="Company not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param CompanyRepository $companyRepository
	 * @return JsonResponse
	 * @throws Exception
	 */
	public function search(Request $request, CompanyRepository $companyRepository)
	{
		$form = $this->submitForm(CompanySearchType::class, $request);

		if( !$form->isValid() )
			return $this->respondBadRequest("Invalid arguments.", $this->getErrors($form));

		$criteria = $form->getData();
		list($limit, $offset) = $this->getPagination($request);

		if( !$companies = $companyRepository->findBy([$criteria['key']=>$criteria['value']], null, $limit, $offset) )
			return $this->respondNotFound('Company not found');

		return $this->respondOK([
			'count'=>count($companies),
			'items'=>$companyRepository->hydrateAll($companies)
		]);
	}


    /**
     * Update company
     *
     * @IsGranted("ROLE_COMPANY")
     * @IsGranted("ROLE_MEMBER")
     *
     * @Route("/company/{id}", methods={"POST"}, requirements={"id"="\d+"})
     *
     * @SWG\Parameter( name="company", in="body", required=true, description="Company information", @SWG\Schema( type="object",
     *     @SWG\Property(property="phone", type="string"),
     *     @SWG\Property(property="sales", type="integer"),
     *     @SWG\Property(property="email", type="string"),
     *     @SWG\Property(property="website", type="string"),
     *     @SWG\Property(property="facebook", type="string"),
     *     @SWG\Property(property="logo", type="string", format="binary"),
     *     @SWG\Property(property="twitter", type="string")
     * ))
     *
     * @SWG\Response(response=200, description="Returns a company")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param EudonetAction $eudonet
     * @param EudoEntityMetadataRepository $entityMetadataRepository
     * @param RoleRepository $roleRepository
     * @param $id
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     */
	public function updateCompany(Request $request, EudonetAction $eudonet, EudoEntityMetadataRepository $entityMetadataRepository, RoleRepository $roleRepository, $id)
	{
		$user = $this->getUser();

		/** @var Company $company */
		$company = $user->getCompany();

		if( $id != $company->getId() )
			return $this->respondNotFound();

		$form = $this->submitForm(CompanyUpdateType::class, $request, $company, false);

		if( !$form->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

		if( $logo = $form->get('logo')->getData() ){

			[$directory, $filename] = $this->moveUploadedFile($logo, 'logo_directory');
			$company->setLogo($filename);
		}

        if( $request->request->has('roles') ) {

            if( !$companyMetadata = $entityMetadataRepository->findByEntity($company) )
                $companyMetadata = $entityMetadataRepository->create($company);

            $requested_roles = $request->get('roles');
            $roles = [];

            foreach (['otherCollaborator','commercialAgent','realEstateAgent'] as $group){

                if( !isset($requested_roles[$group]) )
                    continue;

                $roles[$group] = $roleRepository->findRolesIdByName($requested_roles[$group]);
            }

            $companyMetadata->setData('roles', $roles);

            $entityMetadataRepository->save($companyMetadata);
        }

		$eudonet->push($company);

		return $this->respondOK();
	}


	/**
	 * Get contacts
	 *
	 * @IsGranted("ROLE_CLIENT")
	 *
	 * @Route("/company/contact", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Returns a contact list")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=404, description="Company not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
     * @param Request $request
	 * @param CompanyRepository $companyRepository
	 * @param ContactRepository $contactRepository
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 */
	public function listContacts(Request $request, CompanyRepository $companyRepository, ContactRepository $contactRepository)
	{
		$user = $this->getUser();

		list($limit, $offset) = $this->getPagination($request);

		if( !$company = $user->getCompany() )
			return $this->respondNotFound('Company not found');

		$search = $request->get('search');
		$active = filter_var($request->get('active')??true, FILTER_VALIDATE_BOOLEAN);

		/** @var Contact[] $contacts */
		$contacts = $companyRepository->getContacts($company, $active, ['search'=>$search], $limit, $offset, true);

		$items = [];

		foreach ($contacts as $contact){

			$item = $contactRepository->hydrate($contact, $contactRepository::$HYDRATE_FULL, $company);

            if( $user->hasRole('ROLE_COMPANY') ){

                $item['elearning'] = false;

                if( $contact->getELearningV2() ){

                    $item['elearning'] = [
                        'version'=>2,
                        'email'=>false
                    ];
                }
                elseif( $contact->hasElearningAccount() ){

                    $item['elearning'] = [
                        'version'=>1,
                        'email'=>$contact->getElearningEmail()
                    ];
                }
            }

			$items[] = $item;
		}

		return $this->respondOK([
			'count'=>count($contacts),
			'items'=>$items
		]);
	}


    /**
     * Associate contact
     *
     * @IsGranted("ROLE_COMPANY")
     * @IsGranted("ROLE_MEMBER")
     *
     * @Route("/company/contact/{id}", methods={"POST"}, requirements={"id"="\d+"})
     *
     * @SWG\Response(response=200, description="Returns a contact list")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=404, description="Company not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param EudonetAction $eudonet
     * @param ContactRepository $contactRepository
     * @param CompanyRepository $companyRepository
     * @param AddressRepository $addressRepository
     * @param Request $request
     * @param $id
     * @return JsonResponse
     * @throws ExceptionInterface
     */
	public function associateContact(EudonetAction $eudonet, ContactRepository $contactRepository, CompanyRepository $companyRepository, AddressRepository $addressRepository, Request $request, $id)
	{
		$user = $this->getUser();

		if( !$contact = $contactRepository->find($id) )
			return $this->respondNotFound('Contact not found');

		$company = $user->getCompany();

		if( $companyRepository->getContact($company, $id) )
			return $this->respondError('This contact is already associated');

		$address = new Address();
		$addressForm = $this->submitForm(AddressType::class, $request, $address);

		if( !$addressForm->isValid() )
			return $this->respondBadRequest('Invalid arguments', $this->getErrors($addressForm));

		$eudonet->createAddress($address, $contact, $company);

		return $this->respondCreated();
	}


	/**
	 * Get representatives contact
	 *
	 * @IsGranted("ROLE_COMPANY")
	 * @IsGranted("ROLE_MEMBER")
	 *
	 * @Route("/company/representatives", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Returns a contact")
	 * @SWG\Response(response=404, description="Contact not found")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param UserRepository $userRepository
	 * @param ContactRepository $contactRepository
	 * @param AddressRepository $addressRepository
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 */
	public function getRepresentatives(UserRepository $userRepository, ContactRepository $contactRepository, AddressRepository $addressRepository)
	{
		$user = $this->getUser();

		/** @var Company $company */
		$company = $user->getCompany();

		if( !$legalRepresentatives = $company->getLegalRepresentatives() )
			return $this->respondNotFound();

		$representatives = [];

		foreach ($legalRepresentatives as $contact){

			$data = $contactRepository->hydrate($contact, $contactRepository::$HYDRATE_FULL);

            $data['hasAccount'] = $userRepository->hasAccount($contact, $company);
            $data['isLegalRepresentative'] = true;
			$data['email'] = $contact->getEmail($company);

            $workAddress = $contact->getWorkAddress($company);
            $homeAddress = $contact->getHomeAddress();

            $data['addresses'] = [
                'home'=>$addressRepository->hydrate($homeAddress),
                'work'=>$addressRepository->hydrate($workAddress)
            ];

			$representatives[] = $data;
		}

		return $this->respondOK($representatives);
	}


	/**
	 * Get software list
	 *
	 * @IsGranted("ROLE_COMPANY")
	 *
	 * @Route("/company/software", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Returns a software list")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param ParameterBagInterface $params
	 * @return JsonResponse
	 */
	public function getSoftware(ParameterBagInterface $params)
	{
		$eudonet = $params->get('eudonet');

		$softwares = array_keys($eudonet['constants']['softwares']);
        $softwares = array_diff($softwares, ["SNPI ACCESS"]);

		return $this->respondOK($softwares);
	}


	/**
	 * Set software
	 *
	 * @IsGranted("ROLE_COMPANY")
	 * @IsGranted("ROLE_MEMBER")
	 *
	 * @Route("/company/software", methods={"PUT"})
	 *
	 * @SWG\Response(response=200, description="Returns ok")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param CompanyRepository $companyRepository
	 * @param ParameterBagInterface $params
	 * @param EudonetConnector $eudonetConnector
	 * @param Mailer $mailer
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function setSoftware(Request $request, CompanyRepository $companyRepository, ParameterBagInterface $params, EudonetConnector $eudonetConnector, Mailer $mailer)
	{
		$user = $this->getUser();

		/** @var Company $company */
		$company = $user->getCompany();

		$eudonet = $params->get('eudonet');
		$softwares = $eudonet['constants']['softwares'];

		if( $softwareName = $request->get('customSoftware') ){

			$software = $softwares['SNPI ACCESS'];
			$softwareName = filter_var($softwareName, FILTER_SANITIZE_STRING);
			$company->setSoftware('SNPI ACCESS');
		}
		else{

			if( !$softwareName = $request->get('software') )
				return $this->respondNotFound('Software not found');

			$software = $softwares[$softwareName];
			$company->setSoftware($softwareName);
		}

		$eudonetConnector->update('company', $company->getId(), ['software'=>$software]);

		$companyRepository->save($company);

		$message = [
			'N° : '.$company->getMemberId(),
			'Société : '.$company->getBrand(),
			'Email : '.$company->getEmail(),
			'Nom : '.$company->getName(),
			'Adresse : '.$company->getStreet().' '.$company->getZip().' '.$company->getCity(),
			'Siren : '.$company->getSiren(),
			'Téléphone : '.$company->getPhone(),
			'Logiciel : '.$softwareName
		];

		$mailer->sendMessage($_ENV['ACTIVATION_TO'], $_ENV['ACTIVATION_SUBJECT'], implode('<br>', $message));

		return $this->respondOK();
	}
}
