<?php

namespace App\Security;

use App\Entity\User;
use App\Service\AuthenticationService;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserTokenInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator as BaseJWTTokenAuthenticator;

/**
 * JWTTokenAuthenticator (Guard implementation).
 *
 * @see http://knpuniversity.com/screencast/symfony-rest4/jwt-guard-authenticator
 *
 * @author Nicolas Cabot <n.cabot@lexik.fr>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class JWTTokenAuthenticator extends BaseJWTTokenAuthenticator
{
	/** @var AuthenticationService */
	private $authenticationService;

	public function __construct(JWTTokenManagerInterface $jwtManager, EventDispatcherInterface $dispatcher, TokenExtractorInterface $tokenExtractor, TokenStorageInterface $preAuthenticationTokenStorage, AuthenticationService $authenticationService) {

		parent::__construct($jwtManager, $dispatcher, $tokenExtractor, $preAuthenticationTokenStorage);

		$this->authenticationService = $authenticationService;
	}

	/**
	 * Returns a decoded JWT token extracted from a request.
	 *
	 * {@inheritdoc}
	 *
	 * @return PreAuthenticationJWTUserTokenInterface
	 *
	 * @throws InvalidTokenException If an error occur while decoding the token
	 * @throws ExpiredTokenException If the request token is expired
	 */
	public function getCredentials(Request $request)
	{
		$preAuthToken = parent::getCredentials($request);
		$payload = $preAuthToken->getPayload();

		if ( !($payload['ip']??false) || $payload['ip'] != $request->getClientIp())
			throw new InvalidTokenException('Invalid JWT Token (IP)');

		return $preAuthToken;
	}

	public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
	{
		/** @var User $user */
		$user = $token->getUser();

        define('CURRENT_USER_ID',  $user->getId());
        define('CURRENT_USER_TYPE',  $user->getType());

		$securityCookieSecret = $request->cookies->get(AuthenticationService::SECURITY_COOKIE_NAME);

		if (null === $securityCookieSecret || !$this->authenticationService->isSecurityHashValid($securityCookieSecret, $user))
			return $this->onAuthenticationFailure($request, new AuthenticationException('Wrong security cookie'));

		return null;
	}
}
