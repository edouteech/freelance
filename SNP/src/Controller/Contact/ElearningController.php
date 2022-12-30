<?php

namespace App\Controller\Contact;

use App\Controller\AbstractController;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use App\Service\ElearningConnector;
use App\Service\EudonetAction;
use App\Service\Mailer;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Swagger\Annotations as SWG;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Contact Controller
 *
 * @SWG\Tag(name="Contact e-learning")
 *
 * @Security(name="Authorization")
 * @IsGranted("ROLE_MEMBER")
 *
 */
class ElearningController extends AbstractController
{
	/**
	 * Request new password
	 *
	 * @Route("/contact/{id}/elearning/password", methods={"GET"})
	 *
	 * @SWG\Response(response=200, description="Token sent")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param $id
	 * @param Mailer $mailer
	 * @param UserRepository $userRepository
	 * @param ContactRepository $contactRepository
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function requestElearningPassword($id, Mailer $mailer, UserRepository $userRepository, ContactRepository $contactRepository)
	{
		$user = $this->getUser();

		if( !$contact = $userRepository->getContact($user, $id) )
			return $this->respondNotFound('Contact not found');

		if( !$email = $user->getEmail() )
			return $this->respondNotFound('Email not found');

		$contact->setToken(true);
		$contactRepository->save($contact);

		$bodyMail = $mailer->createBodyMail('resetting/mail.html.twig', ['title'=>'Renouvellement du mot de passe e-learning', 'contact'=>$contact, 'token'=>$contact->getToken()]);
		$mailer->sendMessage($email, 'Renouvellement du mot de passe e-learning '.$contact->getCivility().' '.$contact->getLastname(), $bodyMail);

		return $this->respondOK(['email'=>$this->obfuscateEmail($email)]);
	}


	/**
	 * Reset password
	 *
	 * @Route("/contact/{id}/elearning/password", methods={"POST"})
	 *
	 * @SWG\Parameter( name="user", in="body", required=true, description="Contact informations", @SWG\Schema( type="object",
	 *     @SWG\Property(property="token", type="string", example="fdg5468esd5468ewds654"),
	 *     @SWG\Property(property="password", type="string", example="*****"),
	 * ))
	 *
	 * @SWG\Response(response=200, description="Password updated")
	 * @SWG\Response(response=500, description="Internal server error")
	 *
	 * @param $id
	 * @param Mailer $mailer
	 * @param Request $request
	 * @param UserRepository $userRepository
	 * @param ElearningConnector $elearningConnector
	 * @param EudonetAction $eudonet
	 * @return JsonResponse
	 * @throws ExceptionInterface
	 * @throws Exception
	 */
	public function resetElearningPassword($id, Mailer $mailer, Request $request, UserRepository $userRepository, ElearningConnector $elearningConnector, EudonetAction $eudonet)
	{
		$user = $this->getUser();

		if( !$token = $request->get('token') )
			return $this->respondBadRequest('Missing parameters', ['token'=>'Invalid arguments']);

		if( !$plain_password = $request->get('password') )
			return $this->respondBadRequest('Missing parameters', ['password'=>'Invalid arguments']);

		$token = trim(strip_tags($token));

		if( !$contact = $userRepository->getContact($user, $id) )
			return $this->respondNotFound('Contact not found.');

		if( $contact->getToken() === null || $token !== $contact->getToken() )
			return $this->respondError('Token is invalid');

		if( !$contact->isPasswordRequestedInTime() )
			return $this->respondError('Token has expired');

		$elearningConnector->updateUser($contact->getElearningToken(), ['passwd'=>$plain_password]);

        if( !$contact->getElearningEmail() )
            $contact->setElearningEmail($user->getEmail());

		$contact->setElearningPassword($plain_password);
		$contact->setToken(null);

		$eudonet->push($contact);

		$bodyMail = $mailer->createBodyMail('e-learning/account.html.twig', ['title'=>'Renouvellement du mot de passe e-learning', 'contact' => $contact]);
		$mailer->sendMessage($contact->getElearningEmail(), 'Renouvellement du mot de passe e-learning', $bodyMail);

		return $this->respondOK();
	}
}
