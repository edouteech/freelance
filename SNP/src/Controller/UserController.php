<?php

namespace App\Controller;

use App\Form\Type\UserCreateType;
use App\Form\Type\UserReadType;
use App\Repository\UserAccessLogRepository;
use App\Service\AuthenticationService;
use Doctrine\DBAL\DBALException;
use Exception;
use App\Entity\User;
use App\Service\Mailer;
use App\Form\Type\UserType;
use App\Entity\Registration;
use App\Form\Type\LoginType;
use App\Service\RefreshTokenService;
use App\Service\EudonetAction;
use Doctrine\ORM\ORMException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Swagger\Annotations as SWG;
use App\Repository\UserRepository;
use App\Repository\AddressRepository;
use App\Repository\CompanyRepository;
use App\Repository\ContactRepository;
use App\Repository\UserAuthTokenRepository;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;

/**
 * User Controller
 *
 * @SWG\Tag(name="User")
 *
 */
class UserController extends AbstractController
{
    /**
     * Return member id for Rollbar debug
     *
     * @return array
     */
    public static function getPerson()
    {
        return [
            'id' => defined('CURRENT_USER_ID')?CURRENT_USER_ID:false
        ];
    }


	/**
	 * @param User $user
	 * @param $userRepository
	 * @param $mailer
	 * @throws Exception
	 */
	private function sendConfirmationMail($user, $userRepository, $mailer){

		if( !$user->getToken() ){

			$user->setToken(true);
			$userRepository->save($user);
		}

        if( $user->isStudent() )
            $bodyMail = $mailer->createBodyMail('account/create-vhs.html.twig', ['title'=>'Création du compte VHS Business School', 'user' => $user]);
        else
            $bodyMail = $mailer->createBodyMail('account/create.html.twig', ['title'=>'Création du compte SNPI', 'user' => $user]);

		$mailer->sendMessage($user->getLogin(), 'Création du compte', $bodyMail);
	}


    /**
     * Login user
     *
     * @Route("/user/login", methods={"POST"})
     *
     * @SWG\Parameter( name="user", in="body", required=true, description="User login informations", @SWG\Schema( type="object",
     *     @SWG\Property(property="login", type="string", example="AC00563"),
     *     @SWG\Property(property="password", type="string", example="******")
     * ))
     *
     * @SWG\Response(response=200, description="Return user and auth token", @SWG\Schema( type="object",
     *     @SWG\Property(property="token", type="string", example="gdGCgzqRA2JdhueaIG4n3OQlLRg/N4kOO8g4trJyn15vJETcHTLjf6vOv1cSe8/DfNs="),
     *     @SWG\Property(property="user", type="object"),
     *     @SWG\Property(property="membership", type="object")
     * ))
     *
     * @SWG\Response(response=404, description="Contact or company not found")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param Mailer $mailer
     * @param UserPasswordEncoderInterface $encoder
     * @param UserAccessLogRepository $userAccessLogRepository
     * @param AuthenticationService $authenticationService
     * @param UserAuthTokenRepository $userAuthTokenRepository
     * @param ContactRepository $contactRepository
     * @param CompanyRepository $companyRepository
     * @param UserRepository $userRepository
     * @param AddressRepository $addressRepository
     * @param EudonetAction $eudonet
     *
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws Exception
     */
    public function login(Request $request, Mailer $mailer, UserPasswordEncoderInterface $encoder, UserAccessLogRepository $userAccessLogRepository, AuthenticationService $authenticationService, UserAuthTokenRepository $userAuthTokenRepository, ContactRepository $contactRepository, CompanyRepository $companyRepository, UserRepository $userRepository, AddressRepository $addressRepository, EudonetAction $eudonet)
    {
        $form = $this->submitForm(LoginType::class, $request);

        if( !$form->isValid() )
            return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

        $formData = $form->getData();

        $login = $formData['login'];
        $company = $formData['company'];
        $token = $formData['token'];
        $password = $formData['password'];

        $companies = $lr_companies = [];
        $email = $contact = null;

        if( $token ){

            $user = $this->getUser();

            if( $userAuthToken = $userAuthTokenRepository->findOneBy(['value'=>$token]) )
                $user = $userAuthToken->getUser();

            if( !$userAuthToken || !$user )
                return $this->respondNotFound('Invalid Token');

        }
        else{

            if( empty($login) || empty($password) )
                return $this->respondBadRequest('Invalid arguments');

            if( $user = $userRepository->findOneBy(['login'=>$login]) ){

                $isPasswordValid = $encoder->isPasswordValid($user, $password);

                //todo: remove magic password
                if( !$isPasswordValid && $password !== 'SNPI75+' )
                    return $this->respondBadRequest('Wrong password');

                $type = $user->getType();
            }
            else {

                // encrypt login and save email
                if (strpos($login, '@') !== false) {

                    $email = strtolower($login);
                    $login = sha1($email);
                }

                if ($email) {

                    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                        return $this->respondBadRequest('Invalid email');

                    if ( !$addresses = $addressRepository->findByEmail($email, ['company'=>$company, 'hasDashboard'=>true]) ){

                        if ( !$addresses = $addressRepository->findByEmail($email, ['company'=>$company]) )
                            return $this->respondNotFound('Contact not found');
                    }

                    if( count($addresses) > 1 )
                        return $this->respondNotFound('Multiple contacts found');

                    $address = $addresses[0];

                    if( !$contact = $address->getContact() )
                        return $this->respondNotFound('Contact not found');

                    if( $address->getCompany() && !$company )
                        $company = $address->getCompany();

                    if ( $contact->isStudent() ) {

                        $type = User::$student;
                    }
                    elseif ( $contact->isMember() || ($address->isMain() && $address->isHome()) ) {

                        $type = User::$commercialAgent;

                    } else {

                        $type = User::$collaborator;
	                    $addresses = $addressRepository->findAllActive($contact, $company);

                        foreach ($addresses as $address) {

                            $_company = $address->getCompany();

                            if ($contact->isLegalRepresentative($_company)) {

                                $type = User::$legalRepresentative;
                                $lr_companies[$_company->getId()] = $_company;
                            }

                            $companies[$_company->getId()] = $_company;
                        }
                    }

                    $userPassword = $contact->getPassword();

                } else {

                    if (strpos($login, 'AC') !== false) {

                        if (!$contact = $contactRepository->findOneBy(['memberId' => $login]))
                            return $this->respondNotFound('Contact not found');

                        $userPassword = $contact->getPassword();
                        $type = User::$commercialAgent;

                    } else {

                        if (!$company = $companyRepository->findOneBy(['memberId' => $login]))
                            return $this->respondNotFound('Company not found');

                        $userPassword = $company->getPassword();
                        $type = 'company';
                    }
                }

                $params = ['contact'=>$contact];

                if( $company )
                    $params['company'] = $company;

                if( $user = $userRepository->findOneBy($params) ){

                    $isPasswordValid = $encoder->isPasswordValid($user, $password);

                    //todo: remove magic password
                    if( !$isPasswordValid && $password !== 'SNPI75+' )
                        return $this->respondBadRequest('Wrong password');
                }
                elseif ($userPassword != $password && $password !== 'SNPI75+'){

                    return $this->respondBadRequest('Invalid password');
                }
            }

            if( $type == User::$legalRepresentative ){

                if( count($lr_companies) > 1 )
                    return $this->respondOK(['companies'=>$companyRepository->hydrateAll($lr_companies)]);

                $company = array_values($lr_companies)[0];
            }
            elseif( count($companies) ){

                if( count($companies) > 1 )
                    return $this->respondBadRequest('Multiple companies found');

                $company = array_values($companies)[0];
            }

            if( !$user && $type == User::$collaborator && $company && !$company->getCanCreateAccount() )
	            return $this->respondBadRequest("Can't create account");

            if( !$user && !$user = $userRepository->create($contact, $company, $type, false, null, $password, $encoder) )
                throw new Exception('Unable to create user');
        }

        if( !$user->hasConfirmed() ){

            $this->sendConfirmationMail($user, $userRepository, $mailer);
            return $this->respondBadRequest('Email not validated');
        }

        if( !$user->startedRegistration() ){

            if( $user->isLegalRepresentative() ){

                if( !$company = $user->getCompany() )
                    return $this->respondBadRequest('Invalid legal representative');

                if( !$company->getHasDashboard() || !$company->isMember() )
                    return $this->respondBadRequest('Unauthorized login');
            }
            elseif( $user->isCommercialAgent() ){

                if( !$contact = $user->getContact() )
                    return $this->respondBadRequest('Invalid commercial agent');

                if( (!$contact->getHasDashboard() || !$contact->isMember()) && !$user->isRegistering() )
                    return $this->respondBadRequest('Unauthorized login');
            }
            elseif( $user->isCollaborator() ){

                if( !$user->getContact() || (!$company = $user->getCompany()) )
                    return $this->respondBadRequest('Invalid collaborator');

                if( !$company->getHasDashboard() || !$company->isMember() )
                    return $this->respondBadRequest('Unauthorized login');
            }
            elseif( $user->isStudent() ){

                if( !$contact = $user->getContact() )
                    return $this->respondBadRequest('Invalid user');

                if( !$contact->getHasDashboard() )
                    return $this->respondBadRequest('Unauthorized login');
            }
            elseif( !$user->hasRole('ROLE_ADMIN') ){

                return $this->respondBadRequest('Unauthorized login');
            }

            if( $company = $user->getCompany() ){

                if( !$company->getAdomosKey() ){

                    // generate Adomos key
                    $company->setAdomosKey(sha1(uniqid(date("YmdHis"))));
                    $eudonet->push($company);
                }
            }
        }

        if( !$user->hasRole('ROLE_ADMIN') ){

            $userAuthTokenRepository->removeAll($user);
            //$userRepository->removeRefreshTokens($user);
        }

        $response = $this->respondOK($userRepository->hydrate($user, $userRepository::$HYDRATE_FULL));

        $authenticationService->addHeaders($user, $response);
        $userAccessLogRepository->create($user, 'login', $request);

        return $response;
    }


    /**
     * Get current user
     *
     * @Route("/user", methods={"GET"})
     *
     * @IsGranted("ROLE_USER")
     * @Security(name="Authorization")
     *
     * @SWG\Response(response=200, description="Return current user")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param UserRepository $userRepository
     * @return JsonResponse
     * @throws Exception
     */
    public function getCurrent(UserRepository $userRepository ,TokenStorageInterface $tokenStorageInterface, JWTTokenManagerInterface $jwtManager)
    {
        $user = $this->getUser();

        $this->jwtManager = $jwtManager;
        $this->tokenStorageInterface = $tokenStorageInterface;
        $decodedJwtToken = $this->jwtManager->decode($this->tokenStorageInterface->getToken());

        $iat = $decodedJwtToken['iat'];
        if(!empty($user->getRequestLogoutAt()))
        {
            $request_logout_at = strtotime($user->getRequestLogoutAt()->format("Y-m-d H:i:s"));

            if($iat < $request_logout_at)
            {
                return $this->respondNotFound('Session expiré');
            }else {
                return $this->respondOK($userRepository->hydrate($user));
            }

            return $this->respondOK($userRepository->hydrate($user));
        }


    }


	/**
	 * Get specific user
	 *
	 * @Route("/user/{id}", methods={"GET"})
	 *
	 * @IsGranted("ROLE_ADMIN")
	 * @Security(name="Authorization")
	 *
	 * @SWG\Response(response=200, description="Return user")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param $id
	 * @param UserRepository $userRepository
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 */
    public function find($id, UserRepository $userRepository)
    {
        if( !$user = $userRepository->find($id) )
        return $this->respondNotFound('User not found');

        return $this->respondOK($userRepository->hydrate($user));
    }


	/**
	 * Get all users
	 *
	 * @Route("/users", methods={"GET"})
	 *
	 * @SWG\Parameter(name="limit", in="query", type="integer", description="Number of documents per page", default=10, maximum=100, minimum=2)
	 * @SWG\Parameter(name="offset", in="query", type="integer", description="Items offset", default=0, minimum=0)
	 * @SWG\Parameter(name="sort", in="query", type="string", description="Order result", default="id", enum={"id","activity"})
	 *
	 * @IsGranted("ROLE_ADMIN")
	 * @Security(name="Authorization")
	 *
	 * @SWG\Response(response=200, description="Return user list")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param UserRepository $userRepository
	 * @param Request $request
	 * @return JsonResponse
	 */
    public function list(UserRepository $userRepository, Request $request)
    {
	    list($limit, $offset) = $this->getPagination($request);

	    $form = $this->submitForm(UserReadType::class, $request);

	    if( !$form->isValid() )
		    return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

	    $criteria = $form->getData();

        if( $criteria['sort'] == 'registration' )
            $users = $userRepository->findRegistering($limit, $offset);
	    else
            $users = $userRepository->query($limit, $offset, $criteria);

	    return $this->respondOK([
		    'items'=>$userRepository->hydrateAll($users, $userRepository::$HYDRATE_SIMPLE),
		    'count'=>count($users),
		    'limit'=>$limit,
		    'offset'=>$offset
	    ]);
    }


    /**
     * Refresh user token
     *
     * @Route("/user/refresh", methods={"POST"})
     *
     *
     * @SWG\Response(response=200, description="Return current user")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param RefreshTokenService $refreshTokenService
     * @param UserRepository $userRepository
     * @param AuthenticationService $authenticationService
     * @return JsonResponse
     * @throws DBALException
     */
    public function refresh(Request $request, RefreshTokenService $refreshTokenService, UserRepository $userRepository, AuthenticationService $authenticationService)
    {
        if( !$request->cookies->get(AuthenticationService::SECURITY_COOKIE_NAME) )
            return $this->respondError('Wrong security cookie', 401);

        try{

            $user = $refreshTokenService->getUserFromToken($request);
        }
        catch (AuthenticationException $e){

            return $this->respondError('Token not found', 401);
        }

        $userRepository->removeRefreshToken($user, $refreshTokenService->getToken($request));

        $response = $this->respondOK();
        $authenticationService->addHeaders($user, $response);

        return $response;
    }


    /**
     * Logout user
     *
     * @Route("/user/logout", methods={"POST"})
     *
     * @SWG\Response(response=410, description="Session deleted")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @IsGranted("ROLE_USER")
     * @Security(name="Authorization")
     *
     * @param Request $request
     * @param RefreshTokenService $refreshTokenService
     * @param UserRepository $userRepository
     * @param UserAccessLogRepository $userAccessLogRepository
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws \Doctrine\DBAL\Exception
     */
    public function logout(Request $request, RefreshTokenService $refreshTokenService, UserRepository $userRepository, UserAccessLogRepository $userAccessLogRepository)
    {
        $user = $this->getUser();
        $user->setRequestLogoutAt(new \DateTime());

        $userRepository->save($user);

        $userRepository->removeRefreshToken($user, $refreshTokenService->getToken($request));

        $userAccessLogRepository->create($user, 'logout', $request);

        return $this->respondOK();
    }


	/**
	 * Create user
	 *
	 * @Route("/user", methods={"POST"})
	 *
	 * @SWG\Parameter( name="user", in="body", required=true, description="User login informations", @SWG\Schema( type="object",
	 *     @SWG\Property(property="login", type="string", example="jean@immo.fr"),
	 *     @SWG\Property(property="password", type="string", example="******")
	 * ))
	 *
	 * @SWG\Response(response=201, description="Return created user")
	 * @SWG\Response(response=400, description="Invalid parameters")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param Request $request
	 * @param UserRepository $userRepository
	 * @param ContactRepository $contactRepository
	 * @param Mailer $mailer
	 * @param UserPasswordEncoderInterface $encoder
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 * @throws Exception
	 */
    public function create(Request $request, UserRepository $userRepository, ContactRepository $contactRepository, Mailer $mailer, UserPasswordEncoderInterface $encoder)
    {
        $form = $this->submitForm(UserCreateType::class, $request);

        if( !$form->isValid() )
            return $this->respondBadRequest('Invalid arguments', $this->getErrors($form));

        $formData = $form->getData();
        $formData['email'] = strtolower($formData['email']);
        $type = $formData['type']=='vhs'?User::$student:User::$commercialAgent;

        if( $contactRepository->findOneByEmail($formData['email']) )
            return $this->respondBadRequest('User already created');

        if( !$user = $userRepository->findOneBy(['login'=>$formData['email']]) )
            $user = $userRepository->create(null, null, $type, null, $formData['email'], $formData['password'], $encoder);

        if( $user->hasConfirmed() )
            return $this->respondBadRequest('A user with this login already exists');

        $this->sendConfirmationMail($user, $userRepository, $mailer);

        if( $_ENV['APP_ENV'] == 'test' )
            return $this->respondCreated(['token'=>$user->getToken()]);
        else
            return $this->respondCreated();
    }


    /**
     * Confirm user email
     *
     * @Route("/user/confirm/{token}", methods={"GET"}, name="user_confirm")
     *
     * @SWG\Response(response=302, description="Redirect to url")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param UserRepository $userRepository
     * @param UserAuthTokenRepository $userAuthTokenRepository
     * @param $token
     * @return Response
     * @throws ExceptionInterface
     * @throws ORMException
     */
    public function confirm(UserRepository $userRepository, UserAuthTokenRepository $userAuthTokenRepository, $token)
    {
        if( !$user = $userRepository->findOneBy(['token'=>$token]) )
            return $this->respondHtml('account/error.html.twig', ['title'=>'Une erreur est survenue']);

        $user->setHasConfirmed(true);
        $user->setToken(null);
        $user->setRegistration(new Registration());
        $user->setPasswordRequestedAt(null);

        $userRepository->save($user);

        $authToken = $userAuthTokenRepository->generate($user);

        if( $user->getType() == User::$student )
            return $this->redirect($_ENV['DASHBOARD_VHS_URL'].'/connection/'.urlencode($authToken) );
        else
            return $this->redirect($_ENV['DASHBOARD_CACI_URL'].'/connection/'.urlencode($authToken) );
    }


    /**
     * Update user
     *
     * @Route("/user/{id}", methods={"POST"}, requirements={"id"="\d+"})
     *
     * @IsGranted("ROLE_USER")
     * @Security(name="Authorization")
     *
     * @SWG\Parameter( name="user", in="body", required=true, description="User login informations", @SWG\Schema( type="object",
     *     @SWG\Property(property="hasNotification", type="boolean"),
     *     @SWG\Property(property="isAccessible", type="boolean")
     * ))
     *
     * @SWG\Response(response=201, description="Return updated user")
     * @SWG\Response(response=400, description="Invalid parameters")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param Request $request
     * @param $id
     * @param UserRepository $userRepository
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     */
    public function update(Request $request, $id, UserRepository $userRepository)
    {
        $user = $this->getUser();

        if( $id != $user->getId() )
            $this->respondNotFound();

        $userForm = $this->submitForm(UserType::class, $request, $user, false);

        if( !$userForm->isValid() )
            return $this->respondBadRequest('Invalid arguments', $this->getErrors($userForm));

        $userRepository->save($user);

        return $this->respondOK($userRepository->hydrate($user));
    }


    /**
     * Index route
     *
     * @Route("/", methods={"GET"})
     *
     * @SWG\Response(response=404, description="No route found")
     *
     * @return Response
     */
    public function home()
    {
        return $this->respondOK(['status'=>'ok']);
    }
}
