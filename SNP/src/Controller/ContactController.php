<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Appendix;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\User;
use App\Entity\UserAuthToken;
use App\Form\Type\AddressType;
use App\Form\Type\AddressUpdateType;
use App\Form\Type\ContactCreateType;
use App\Form\Type\AddressReadType;
use App\Form\Type\ContactReadType;
use App\Form\Type\ContactUpdateType;
use App\Repository\AddressRepository;
use App\Repository\AppendixRepository;
use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Repository\RoleRepository;
use App\Repository\UserAuthTokenRepository;
use App\Repository\UserRepository;
use App\Service\CaciService;
use App\Service\EudonetAction;
use App\Service\EudonetConnector;
use App\Service\Mailer;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Nelmio\ApiDocBundle\Annotation\Security;

/**
 * Contact Controller
 *
 * @SWG\Tag(name="Contact")
 * @Security(name="Authorization")
 *
 */
class ContactController extends AbstractController
{
    /**
     * Get current contact
     *
     * @IsGranted("ROLE_USER")
     *
     * @Route("/contact", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Return a contact")
     * @SWG\Response(response=404, description="Contact not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param ContactRepository $contactRepository
     * @param AddressRepository $addressRepository
     *
     * @return JsonResponse
     * @throws ExceptionInterface
     */
	public function getCurrent(ContactRepository $contactRepository, AddressRepository $addressRepository)
	{
		$user = $this->getUser();

		if( !$contact = $user->getContact() )
			return $this->respondNotFound("Contact not found");

		$company = $user->getCompany();

		$data = $contactRepository->hydrate($contact, $contactRepository::$HYDRATE_FULL, $company);

		//todo: remove
		$data['address'] = $addressRepository->hydrate($contact->getHomeAddress());

		return $this->respondOK($data);
	}

    /**
     * Get contact
     *
     * @IsGranted("ROLE_CLIENT")
     *
     * @Route("/contact/{id}", methods={"GET"}, requirements={"id"="\d+"})
     *
     * @SWG\Response(response=200, description="Return a contact")
     * @SWG\Response(response=404, description="Contact not found")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param ContactRepository $contactRepository
     * @param CompanyRepository $companyRepository
     * @param UserRepository $userRepository
     * @param $id
     * @return JsonResponse
     * @throws ExceptionInterface
     */
	public function find(ContactRepository $contactRepository, CompanyRepository $companyRepository, UserRepository $userRepository, $id)
	{
		$user = $this->getUser();

        if( !$company = $user->getCompany() )
            return $this->respondNotFound('Company not found');

        if( !$contact = $companyRepository->getContact($company, $id) )
            return $this->respondNotFound('Contact not found');

        $data = $contactRepository->hydrate($contact, $contactRepository::$HYDRATE_FULL);

        if( $contact->isLegalRepresentative($company) )
            return $this->respondOK($data);

        $item = $contactRepository->hydrate($contact, $contactRepository::$HYDRATE_FULL, $company);

        if( $_user = $userRepository->getAccount($contact, $company) )
            $item['roles'] = $userRepository->getRoles($_user, false);

		return $this->respondOK($item);
	}


    /**
     * Create contact
     *
     * @Route("/contact", methods={"POST"})
     *
     * @SWG\Response(response=200, description="Returns a contact")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @SWG\Parameter( name="contact", in="body", required=true, description="Contact information", @SWG\Schema( type="object",
     *     @SWG\Property(property="firstname", type="string"),
     *     @SWG\Property(property="lastname", type="string"),
     *     @SWG\Property(property="civility", type="string", enum={"Monsieur", "Madame"}),
     *     @SWG\Property(property="email", type="string"),
     *     @SWG\Property(property="avatar", type="string"),
     *     @SWG\Property(property="birthday", type="string"),
     *     @SWG\Property(property="birthPlace", type="string"),
     *     @SWG\Property(property="phone", type="string"),
     *     @SWG\Property(property="legalForm", type="string", enum={"EI", "Micro Entrepreneur","EIRL", "Micro Entrepreneur + EIRL"}),
     *     @SWG\Property(property="street", type="string"),
     *     @SWG\Property(property="zip", type="string"),
     *     @SWG\Property(property="isHome", type="boolean"),
     *     @SWG\Property(property="rsac", type="string"),
     *     @SWG\Property(property="city", type="string")
     * ))
     *
     * @IsGranted("ROLE_USER")
     *
     * @param Request $request
     * @param ContactRepository $contactRepository
     * @param Mailer $mailer
     * @param UserRepository $userRepository
     * @param UserPasswordEncoderInterface $encoder
     * @param EudonetAction $eudonet
     * @param CaciService $caciService
     * @return JsonResponse
     *
     * @throws ExceptionInterface
     * @throws NonUniqueResultException
     * @throws ORMException
     */
    public function create(Request $request, ContactRepository $contactRepository, Mailer $mailer, UserRepository $userRepository, UserPasswordEncoderInterface $encoder, EudonetAction $eudonet, CaciService $caciService)
    {
	    /** @var User $user */
	    $user = $this->getUser();

	    $company = $user->getCompany();

	    $contact = new Contact();
        $contactForm = $this->submitForm(ContactCreateType::class, $request, $contact);

        if( !$contactForm->isValid() )
	        return $this->respondBadRequest('Invalid arguments', $this->getErrors($contactForm));

	    $addresses = clone $contact->getAddresses();
	    $contact->removeAddresses();
        foreach( $addresses as $address ){

            if( $address->getEmail() && $contactRepository->findOneByEmail($address->getEmail()) )
	            return $this->respondBadRequest('Contact already created');
        }

	    if( $user->isRegistering() && $user->getType() == User::$commercialAgent ){

		    if( strtolower($contact->getCivility()) == 'madame' )
			    $contact->setPolitePhrase('Chère Adhérente');
		    else
			    $contact->setPolitePhrase('Cher Adhérent');
	    }

		if( $user->getType() == User::$student )
			$contact->setStatus(User::$student);

	    $contact->setPassword($user->getPassword());

	    $eudonet->push($contact);

	    if( !$contact->getId() )
		    return $this->respondInternalServerError("Eudonet can't create contact");

	    if( $avatar = $contactForm->get('avatar')->getData() ){

		    [$directory, $filename] = $this->moveUploadedFile($avatar, 'avatar_directory');
		    $url = $eudonet->uploadImage('contact', $contact->getId(), 'avatar', $directory.'/'.$filename);

		    $contact->setAvatar($url);
		    $eudonet->push($contact);
	    }

	    if( $user->isRegistering() ){

            if( $user->getType() == User::$commercialAgent )
                $caciService->register($user->getRegistration(), $request);

		    $user->setContact($contact);
		    $user->setLogin(null);

		    if( $registration = $user->getRegistration() )
			    $registration->setInformation(true);

		    $userRepository->save($user);
	    }

	    if( $user->isLegalRepresentative() ){

		    foreach ($addresses as $address)
			    $eudonet->createAddress($address, $contact, $company);
	    }
	    else{

            foreach ($addresses as $address)
			    $eudonet->createAddress($address, $contact);
	    }

        $contact->addAddresses($addresses);

        $this->inviteCollaborator($request, $contact, $company, $mailer, $encoder);

        $eudonet->pull($contact);

	    return $this->respondCreated();
    }


	/**
	 * Invite collaborator
	 *
	 * @param Request $request
	 * @param Contact $contact
	 * @param Company|null $company
	 * @param Mailer $mailer
	 * @param UserPasswordEncoderInterface $encoder
	 * @return false|string
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	private function inviteCollaborator(Request $request, Contact $contact, ?Company $company, Mailer $mailer, UserPasswordEncoderInterface $encoder){

		/** @var UserRepository $userRepository */
		$userRepository = $this->entityManager->getRepository(User::class);

		/** @var UserAuthTokenRepository $userAuthTokenRepository */
		$userAuthTokenRepository = $this->entityManager->getRepository(UserAuthToken::class);

		if( $contact->getHasDashboard() && !$contact->getMemberId() ){

			$contactUser = $userRepository->getAccount($contact, $company);

			if( !$contactUser || $contactUser->getChangePassword() ){

				if( !$email = $contact->getEmail($company) )
					throw new Exception('Contact email not found');

				if( $contact->isLegalRepresentative($company) ){

					if( $password = $request->get('password') ){

						if( !$legalRepresentativeUser = $userRepository->create($contact, $company, User::$legalRepresentative, null, null, $password, $encoder) )
							throw new Exception('Cannot create user');

						$token = $userAuthTokenRepository->generate($legalRepresentativeUser);

						$body = $mailer->createBodyMail('account/valid-legal.html.twig', ['title'=>'Compte représentant légal crée !', 'contact'=>$contact, 'company'=>$company, 'token'=>$token]);
						$mailer->sendMessage($email, 'Compte représentant légal crée', $body);
					}
					else{
						$body = $mailer->createBodyMail('account/invite-legal.html.twig', ['title'=>'Activez dès aujourd’hui la multi-connexion', 'contact'=>$contact, 'company'=>$company]);
						$mailer->sendMessage($email, 'Activez dès aujourd’hui la multi-connexion', $body);
					}
				}
				else{

					if( !$collaboratorUser = $userRepository->create($contact, $company, User::$collaborator, true) )
						throw new Exception('Cannot create user');

					$token = $userAuthTokenRepository->generate($collaboratorUser);

					$body = $mailer->createBodyMail('account/invite.html.twig', ['title'=>'Activez dès à présent votre compte collaborateur !', 'contact'=>$contact, 'company'=>$company, 'token'=>$token]);
					$mailer->sendMessage($email, 'Activez votre compte collaborateur', $body);
				}

				return $email;
			}
		}

		return false;
	}


    /**
     * Update contact
     *
     * @Route("/contact/{id}", methods={"POST"}, requirements={"id"="\d+"})
     *
     * @SWG\Response(response=200, description="Returns updated contact")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @SWG\Parameter( name="contact", in="body", required=true, description="Contact information", @SWG\Schema( type="object",
     *     @SWG\Property(property="isActive", type="boolean"),
     *     @SWG\Property(property="firstname", type="string"),
     *     @SWG\Property(property="lastname", type="string"),
     *     @SWG\Property(property="civility", type="string", enum={"Monsieur", "Madame"}),
     *     @SWG\Property(property="email", type="string"),
     *     @SWG\Property(property="avatar", type="string", format="binary"),
     *     @SWG\Property(property="birthday", type="string"),
     *     @SWG\Property(property="issuedAt", type="string"),
     *     @SWG\Property(property="hasDashboard", type="boolean"),
     *     @SWG\Property(property="phone", type="string"),
     *     @SWG\Property(property="legalForm", type="string", enum={"EI", "Micro Entrepreneur","EIRL", "Micro Entrepreneur + EIRL"}),
     *     @SWG\Property(property="street", type="string"),
     *     @SWG\Property(property="zip", type="string"),
     *     @SWG\Property(property="password", type="string"),
     *     @SWG\Property(property="city", type="string"),
     *     @SWG\Property(property="roles", type="array", @SWG\Items(type="string"))
     * ))
     *
     * @IsGranted("ROLE_USER")
     *
     * @param Request $request
     * @param UserPasswordEncoderInterface $encoder
     * @param UserRepository $userRepository
     * @param ContactRepository $contactRepository
     * @param RoleRepository $roleRepository
     * @param EudonetAction $eudonet
     * @param Mailer $mailer
     * @param UserAuthTokenRepository $userAuthTokenRepository
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws NonUniqueResultException
     */
    public function update(Request $request, UserPasswordEncoderInterface $encoder, UserRepository $userRepository, ContactRepository $contactRepository, RoleRepository $roleRepository, EudonetAction $eudonet, Mailer $mailer, UserAuthTokenRepository $userAuthTokenRepository, int $id)
    {
	    $user = $this->getUser();
	    $company = $user->getCompany();
        $email = false;

	    if( !$contact = $userRepository->getContact($user, $id) )
		    return $this->respondNotFound('Contact not found');

        if( !$user->isCommercialAgent() && $contact->isMember() )
            return $this->respondBadRequest('Only member can edit themselves');

    	$hasDashboard = $contact->getHasDashboard();

	    $contactForm = $this->submitForm(ContactUpdateType::class, $request, $contact, false);

        if( !$contactForm->isValid() )
	        return $this->respondBadRequest('Invalid arguments', $this->getErrors($contactForm));

        if( $requestedAddresses = $request->get('addresses') ){

            foreach( $requestedAddresses as $address ){

                if( isset($address['email']) && $contactRepository->findOneByEmail($address['email'], $contact) )
                    return $this->respondBadRequest('Contact already exists');
            }
        }

	    if( $avatar = $contactForm->get('avatar')->getData() ){

		    [$directory, $filename] = $this->moveUploadedFile($avatar, 'avatar_directory');
		    $url = $eudonet->uploadImage('contact', $contact->getId(), 'avatar', $directory.'/'.$filename);

		    $contact->setAvatar($url);
	    }

	    // only legal representative can change this
	    if( !$user->isLegalRepresentative() || $contact->isMember() )
		    $contact->setHasDashboard($hasDashboard);

		$eudonet->push($contact);
	    $contactUser = $userRepository->getAccount($contact, $company);

		if( $contactUser ){

			if( $password = $request->get('password') ){

                $contactUser->setChangePassword(false);
                $contactUser->setPassword($encoder->encodePassword($user, $password));
            }

			if( $request->request->has('roles') ) {

				if( $user->isLegalRepresentative() ){

                    $roles = $request->get('roles');

                    if( empty($roles) )
                        $contactUser->setRoles(null);
                    else
                        $roles = $roleRepository->findRolesByName($request->get('roles'));

                    $contactUser->setRoles($roles);
                }
			}

			$userRepository->save($contactUser);
        }

	    $addresses = $contact->getAddresses();

	    if( $request->get('addresses') ){

		    $indexes = array_keys($request->get('addresses'));

		    foreach ($addresses as $index=>$address) {

			    if( in_array($address->getIndex(), $indexes, true) ){

				    if( !$address->hasCertificate() && ($issuedAt = $address->getIssuedAt()) ){

					    $address->setIssuedAt(null);
					    $address->setHasCertificate(true);
					    $eudonet->push($address);

					    $address->setIssuedAt($issuedAt);
				    }

				    $eudonet->push($address);
			    }
		    }
	    }

	    $email = $this->inviteCollaborator($request, $contact, $company, $mailer, $encoder);

	    $eudonet->pull($contact);

	    if( $email )
            return $this->respondOK(['email'=>$this->obfuscateEmail($email)]);
	    else
            return $this->respondOK();
    }


    /**
     * Update contact address
     *
     * @Route("/contact/address/{id}", methods={"POST"}, requirements={"id"="\d+"})
     *
     * @SWG\Response(response=200, description="Returns updated address")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @SWG\Parameter( name="contact", in="body", required=true, description="Contact information", @SWG\Schema( type="object",
     *     @SWG\Property(property="isActive", type="boolean"),
     *     @SWG\Property(property="isMain", type="boolean"),
     *     @SWG\Property(property="email", type="string"),
     *     @SWG\Property(property="phone", type="string")
     * ))
     *
     * @IsGranted("ROLE_USER")
     *
     * @param Request $request
     * @param EudonetAction $eudonet
     * @param ContactRepository $contactRepository
     * @param AddressRepository $addressRepository
     * @param $id
     *
     * @return JsonResponse
     *
     * @throws ExceptionInterface
     * @throws ORMException
     */
    public function updateAddress(Request $request, EudonetAction $eudonet, ContactRepository $contactRepository, AddressRepository $addressRepository, $id)
    {
	    $user = $this->getUser();
	    $contact = $user->getContact();

	    if( !$address = $contact->getAddressById($id) )
		    return $this->respondNotFound('Address not found');

	    $addressForm = $this->submitForm(AddressUpdateType::class, $request, $address, false);

        if( !$addressForm->isValid() )
	        return $this->respondBadRequest('Invalid arguments', $this->getErrors($addressForm));

        if( $request->get('email') && $addresses = $addressRepository->findByEmail($address->getEmail(), ['hasDashboard'=>true, 'exclude'=>$id]) ){

            if( count($addresses) )
                return $this->respondError('Email already used');
        }

	    if( !$address->hasCertificate() && ($issuedAt = $address->getIssuedAt()) ){

		    $address->setIssuedAt(null);
		    $address->setHasCertificate(true);
		    $eudonet->push($address);

		    $address->setIssuedAt($issuedAt);
	    }

	    $eudonet->push($address);

	    if( $address->isMain() ){

		    $addresses = $contact->getAddresses();

		    foreach ($addresses as $_address){

			    if( $_address->getId() != $address->getId() && $_address->isMain() )
				    $_address->setIsMain(false);
		    }

		    $contactRepository->save($contact);
	    }

        return $this->respondOK();
    }


    /**
     * Find expert in contacts
     *
     * @Route("/contact/expert", methods={"GET"})
     *
     * @IsGranted("ROLE_COMPANY")
     * @IsGranted("ROLE_MEMBER")
     *
     * @SWG\Response(response=200, description="Returns updated contact")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of formations per page", default=10, maximum=100, minimum=2)
     * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
     * @SWG\Parameter(name="sort", in="query", type="string", description="Order sorting", default="startedAt", enum={"distance", "startedAt"})
     * @SWG\Parameter(name="order", in="query", type="string", description="Order result", enum={"asc", "desc"}, default="asc")
     * @SWG\Parameter(name="location", in="query", type="string", description="Lat,lng")
     * @SWG\Parameter(name="distance", in="query", type="integer", description="Distance radius")
     *
     * @param Request $request
     * @param AddressRepository $addressRepository
     * @param ContactRepository $contactRepository
     *
     * @return JsonResponse
     * @throws ExceptionInterface
     */
    public function findExperts(Request $request, AddressRepository $addressRepository, ContactRepository $contactRepository)
    {
	    $user = $this->getUser();

	    list($limit, $offset) = $this->getPagination($request);

	    $form = $this->submitForm(AddressReadType::class, $request);

	    if( !$form->isValid() )
		    return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

	    $criteria = $form->getData();
	    $addresses = $addressRepository->query($user, $limit, $offset, $criteria );
	    $experts = [];

        /** @var Address[] $addresses */
        foreach ($addresses as $address){

		    $contact = $address->getContact();
		    $expert = $contactRepository->hydrate($contact);

		    $expert['address'] = $addressRepository->hydrate($address);
		    $expert['address']['distance'] = $this->getDistance($address, $criteria['location']);
		    $expert['address']['rev'] = false;
		    $expert['address']['trv'] = false;

		    foreach ($contact->getAddresses() as $contactAddress){

		        if( $contactAddress->isActive() ){

                    $expert['address']['rev'] = $expert['address']['rev']||$contactAddress->matchPosition('Agrée REV');
                    $expert['address']['trv'] = $expert['address']['trv']||$contactAddress->matchPosition('Agrée TRV');
                }
		    }

		    $experts[] = $expert;
	    }

        return $this->respondOk([
	        'items'=>$experts,
	        'count'=>count($addresses),
	        'limit'=>$limit,
	        'offset'=>$offset
        ]);
    }


    /**
     * Find contact by email
     *
     * @Route("/contact/search", methods={"GET"})
     *
     * @IsGranted("ROLE_USER")
     *
     * @SWG\Response(response=200, description="Returns updated contact")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @SWG\Parameter(name="limit", in="query", type="string", description="Email")
     *
     * @param Request $request
     * @param AddressRepository $addressRepository
     * @param ContactRepository $contactRepository
     * @param AppendixRepository $appendixRepository
     * @return JsonResponse
     *
     * @throws ExceptionInterface
     */
    public function search(Request $request, AddressRepository $addressRepository, ContactRepository $contactRepository, AppendixRepository $appendixRepository)
    {
	    /** @var User $user */
	    $user = $this->getUser();

		if( !$user->hasRole('ROLE_COMPANY') && !$user->hasRole('ROLE_ADMIN') )
			return $this->respondBadRequest();

	    $form = $this->submitForm(ContactReadType::class, $request);

	    if( !$form->isValid() )
		    return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

	    $criteria = $form->getData();
	    $contact = false;

		if( isset($criteria['email']) ){

			if( !$address = $addressRepository->findOneByEmail($criteria['email'], ['isHome'=>true]) )
				return $this->respondNotFound('Contact not found');

			$contact = $address->getContact();
		}
		elseif( isset($criteria['memberId']) ){

			$contact = $contactRepository->findOneBy(['memberId'=>$criteria['memberId'], 'status'=>'member']);
		}

	    if( !$contact )
		    return $this->respondNotFound('Contact not found');

        return $this->respondOK($contactRepository->hydrate($contact));
    }


	/**
	 * Assign contact to company
	 *
	 * @Route("/contact/company", methods={"POST"})
	 *
	 * @IsGranted({"ROLE_CONTACT"})
	 *
	 * @SWG\Response(response=200, description="Updated contact")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param EudonetAction $eudonetAction
	 * @param AddressRepository $addressRepository
	 *
	 * @return JsonResponse
	 *
	 * @throws ExceptionInterface
	 * @throws Exception
	 */
    public function assign(Request $request, EudonetAction $eudonetAction, AddressRepository $addressRepository)
    {
	    $user = $this->getUser();

	    if( !$user->isCommercialAgent() && !$user->isStudent() )
		    return $this->respondError('User cannot assign company');

	    $address = new Address();

	    $form = $this->submitForm(AddressType::class, $request, $address);

	    if( !$form->isValid() )
		    return $this->respondBadRequest('Invalid arguments.', $this->getErrors($form));

        if( $addresses = $addressRepository->findByEmail($address->getEmail(), ['hasDashboard'=>true]) ){

            if( count($addresses) > 0 )
                return $this->respondError('Email already used');
        }

	    /** @var Contact $contact */
	    $contact = $user->getContact();

	    if( $user->isCommercialAgent() )
            $address->setPosition('Agent commercial');

	    $eudonetAction->createAddress($address, $contact);

	    return $this->respondCreated($addressRepository->hydrate($address));
    }


    /**
     * Pull contact from eudonet
     *
     * @Route("/contact/refresh", methods={"POST"})
     *
     * @IsGranted("ROLE_CONTACT")
     *
     * @SWG\Response(response=200, description="Updated contact")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param EudonetAction $eudonet
     * @param Mailer $mailer
     * @param AppendixRepository $appendixRepository
     * @param EudonetConnector $eudonetConnector
     * @param EudonetAction $eudonetAction
     * @param ParameterBagInterface $parameterBag
     * @return JsonResponse
     *
     * @throws ExceptionInterface
     */
    public function refresh(EudonetAction $eudonet, Mailer $mailer, AppendixRepository $appendixRepository, EudonetConnector $eudonetConnector, EudonetAction $eudonetAction, ParameterBagInterface $parameterBag)
    {
	    $user = $this->getUser();

	    /** @var Contact $contact */
	    $contact = $user->getContact();
        $bodyMail = false;

	    $eudonet->pull($contact);

	    if( $user->isCommercialAgent() && $memberId = $contact->getMemberId() ){

            if( $registration = $user->getRegistration() ){

                if( $registration->getMembershipId() ){

                    $status = $eudonetConnector->getValue('membership', $registration->getMembershipId(), 'status');

                    if( $status == '01 - En cours' ){

                        $eudonetConnector->update('membership', $registration->getMembershipId(), ['status'=>1352]);
                        $bodyMail = $mailer->createBodyMail('account/valid.html.twig', ['title'=>'Compte SNPI validé', 'user'=>$user, 'memberId'=>$memberId]);
                    }
                }

                $folderpath = sprintf('%s/var/storage/registrations/%s', $parameterBag->get('kernel.project_dir'), $registration->getRegistrationFolderName());

                $filesystem = new Filesystem();
                $filesystem->remove($folderpath);

                $appendices = $eudonetAction->getAppendices(['_PP'.$contact->getId().'_', '_PP'.$contact->getId().'.']);
                $appendixRepository->bulkInserts($appendices, 1, false);
            }
	    }
	    elseif( $user->isStudent() && $contact->getAddresses() ){

			if( !$contact->getHasDashboard() ){

				$contact->setHasDashboard(true);
				$eudonetAction->push($contact);
			}

            $bodyMail = $mailer->createBodyMail('account/valid-vhs.html.twig', ['title'=>'Compte VHS validé', 'user'=>$user]);
        }

		if( $bodyMail && $email = $user->getEmail() )
			$mailer->sendMessage($email, 'Compte validé', $bodyMail);

	    return $this->respondOK();
    }
}
