<?php

namespace App\Controller\User;

use App\Controller\AbstractController;
use App\Entity\Contact;
use App\Entity\User;
use App\Repository\AddressRepository;
use App\Repository\UserRepository;
use App\Service\EudonetAction;
use App\Service\Mailer;
use Doctrine\ORM\ORMException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Password Controller
 *
 * @SWG\Tag(name="User password")
 */
class PasswordController extends AbstractController
{

	private function generateToken($key){

		$now = new \DateTime();
		return strtoupper(substr(sha1($key.$_ENV['JWT_COOKIE_SALT'].$now->format('Y-m-d')),0, 6));
	}

    /**
     * Request new password
     *
     * @Route("/user/password/{email}", methods={"GET"})
     *
     * @SWG\Response(response=200, description="Token sent")
     * @SWG\Response(response=500, description="Internal server error")
     *
     * @param $email
     * @param UserPasswordEncoderInterface $encoder
     * @param Mailer $mailer
     * @param UserRepository $userRepository
     * @param AddressRepository $addressRepository
     * @return JsonResponse
     * @throws ExceptionInterface
     * @throws ORMException
     */
	public function requestPassword($email, UserPasswordEncoderInterface $encoder, Mailer $mailer, UserRepository $userRepository, AddressRepository $addressRepository)
	{
	    if( strpos($email, '@') !== false ){

            $email = strtolower($email);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                return $this->respondBadRequest('Invalid email');

            if( $user = $userRepository->findOneBy(['login'=>$email]) ) {

                $token = $this->generateToken($user->getPassword() . $user->getId());
            }
            else {

                if( !$addresses = $addressRepository->findByEmail($email, ['hasDashboard'=>true]) ){

                    if( !$addresses = $addressRepository->findByEmail($email) )
                        return $this->respondNotFound('Contact not found');
                }

                if( count($addresses) > 1 )
                    return $this->respondNotFound('Multiple contacts found');

                $address = $addresses[0];

                if( !$contact = $address->getContact() )
                    return $this->respondNotFound('Contact not found');

                if( !$user = $userRepository->findOneBy(['contact'=>$contact]) ){

                    if ( $contact->isStudent() ) {

                        $type = User::$student;
                    }
                    elseif ( $contact->isMember() || ($address->isMain() && $address->isHome()) ) {

                        $type = User::$commercialAgent;

                    } else {

                        if (!$company = $address->getCompany())
                            return $this->respondNotFound('Company not found');

                        if ( !$contact->isLegalRepresentative($company) )
                            return $this->respondNotFound('Account not found');

                        $type = User::$legalRepresentative;
                    }

                    if( !$user = $userRepository->create($contact, $addresses[0]->getCompany() , $type, false, null, $contact->getPassword(), $encoder) )
                        return $this->respondError('Unable to create user');
                }

                if( !$contact->getHasDashboard() && !$user->isRegistering() )
                    return $this->respondError('Invalid user');

                $token = $this->generateToken($user->getPassword().$user->getId());
            }

            $bodyMail = $mailer->createBodyMail('resetting/mail.html.twig', ['title'=>'Renouvellement du mot de passe', 'token'=>$token]);
            $mailer->sendMessage($email, 'Renouvellement du mot de passe', $bodyMail);

            return $this->respondOK(['email'=>$this->obfuscateEmail($email)]);
        }
        else{

            $login = $email;

            if( !$user = $userRepository->findOneBy(['login'=>$login]) ){

                if( !$representative = $userRepository->getRepresentative($login) )
                    return $this->respondBadRequest('Invalid parameters', ['login'=>'Not found']);

                if( $representative instanceof Contact )
                    $user = $userRepository->create($representative, null, User::$commercialAgent, false, $login, $representative->getPassword(), $encoder);
                else
                    $user = $userRepository->create(null, $representative, 'company', false, $login, $representative->getPassword(), $encoder);
            }

            if( !$email = $user->getEmail() )
                return $this->respondNotFound('Email not found');

            $user->setToken(true);
            $userRepository->save($user);

            $bodyMail = $mailer->createBodyMail('resetting/mail.html.twig', ['title'=>'Renouvellement du mot de passe', 'user'=>$user, 'token'=>$user->getToken()]);
            $mailer->sendMessage($email, 'Renouvellement du mot de passe', $bodyMail);

            return $this->respondOK(['email'=>$this->obfuscateEmail($email)]);
        }
	}


	/**
	 * Reset password
	 *
	 * @Route("/user/password/{email}", methods={"POST"})
	 *
	 * @SWG\Parameter( name="user", in="body", required=true, description="User login informations", @SWG\Schema( type="object",
	 *     @SWG\Property(property="token", type="string", example="FDG546"),
	 *     @SWG\Property(property="password", type="string", example="*****"),
	 * ))
	 *
	 * @SWG\Response(response=200, description="Password updated")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param $email
	 * @param Request $request
	 * @param UserPasswordEncoderInterface $encoder
	 * @param UserRepository $userRepository
	 * @param AddressRepository $addressRepository
	 * @param EudonetAction $eudonetAction
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function resetPassword($email, Request $request, UserPasswordEncoderInterface $encoder, UserRepository $userRepository, AddressRepository $addressRepository, EudonetAction $eudonetAction)
	{
		if( !$token = $request->get('token') )
			return $this->respondBadRequest('Missing parameters', ['token'=>'Invalid arguments']);

		if( !$plain_password = $request->get('password') )
			return $this->respondBadRequest('Missing parameters', ['password'=>'Invalid arguments']);

		$requestToken = trim(strip_tags($token));

		if( strpos($email, '@') !== false ){

            $email = strtolower($email);

            if( !$users = $userRepository->findBy(['login'=>$email]) ){

                if( !$addresses = $addressRepository->findByEmail($email, ['hasDashboard'=>true]) ){

                    if( !$addresses = $addressRepository->findByEmail($email) )
                        return $this->respondNotFound('Email not found');
                }

                $address = $addresses[0];

                if( !$contact = $address->getContact() )
                    return $this->respondNotFound('Contact not found');

                if( !$users = $userRepository->findBy(['contact'=>$contact]) )
                    return $this->respondNotFound('Account not found');
            }

            $token = $this->generateToken($users[0]->getPassword().$users[0]->getId());

            if( $requestToken != $token )
                return $this->respondNotFound('Token is invalid');

            foreach ($users as $user){

                $user->setPassword($encoder->encodePassword($user, $plain_password));
                $userRepository->save($user);
            }
        }
		else{

            $login = $email;

            $user = $userRepository->findOneBy(['login'=>$login]);

            if( !$user )
                return $this->respondBadRequest('Invalid login');

            if( $user->getToken() === null || $token !== $user->getToken() )
                return $this->respondError('Token is invalid');

            if( !$user->isPasswordRequestedInTime() )
                return $this->respondError('Token has expired');

            $password = $encoder->encodePassword($user, $plain_password);
            $user->setPassword($password);
            $user->setToken(null);

            $userRepository->save($user);
        }

        return $this->respondOK();
    }
}
