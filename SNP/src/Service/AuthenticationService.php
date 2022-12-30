<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

use App\Repository\UserRepository;
use DateTime;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\User\UserInterface;

final class AuthenticationService
{
	public const SECURITY_COOKIE_NAME = 'security';

	/** @var RefreshTokenManagerInterface */
	private $refreshTokenManager;

	/** @var RefreshTokenService */
	private $refreshTokenService;

	/** @var JWTTokenManagerInterface */
	private $tokenManager;

	/** @var string */
	private $securityCookieSalt;

	/** @var UserRepository */
	private $userRepository;

	public function __construct(RefreshTokenManagerInterface $refreshTokenManager, JWTTokenManagerInterface $tokenManager, RefreshTokenService $refreshTokenService, UserRepository $userRepository) {

		$this->tokenManager = $tokenManager;
		$this->refreshTokenManager = $refreshTokenManager;
		$this->refreshTokenService = $refreshTokenService;
		$this->securityCookieSalt = $_ENV['JWT_COOKIE_SALT'];
		$this->userRepository = $userRepository;
	}

	public function getToken(UserInterface $user){

		if( $token = $this->tokenManager->create($user) ){

			// update login data
			$user->setLastLoginAt($user->getLoggedAt());
			$user->setLoggedAt(new DateTime());

			$this->userRepository->save($user);
		}

		return $token;
	}

	public function addHeaders(UserInterface $user, $response){

		$response->headers->set('X-Auth-Token', $this->getToken($user));
		$response->headers->set('X-Refresh-Token', $this->refreshTokenService->create($user));
		$response->headers->set('X-Token-Type', 'Bearer');

		if( $securityCookie = $this->createSecurityCookie($user) )
			$response->headers->setCookie($securityCookie);
	}

	public function createSecurityCookie(UserInterface $user): ?Cookie
	{
		/**
		 * This might happen if a user is logged in on one computer and logs out on another computer. On refresh of the
		 * session on the first computer, the refresh tokens will be removed and the user should be logged out.
		 */
		$refreshToken = $this->refreshTokenManager->getLastFromUsername($user->getId());

		if (null === $refreshToken)
			return null;

		$cookieHash = $this->generateSecurityCookieHash($user, $refreshToken);

		return new Cookie(self::SECURITY_COOKIE_NAME,$cookieHash,$refreshToken->getValid(), '/', null, true, true, false, 'none');
	}

	public function isSecurityHashValid(string $securityHash, User $user): bool
	{
		/**
		 * This might happen if a user is logged in on one computer and logs out on another computer. On refresh of the
		 * session on the first computer, the refresh tokens will be removed and the user should be logged out.
		 */
		$refreshToken = $this->refreshTokenManager->getLastFromUsername($user->getId());

		if (null === $refreshToken)
			return false;

		return $securityHash === $this->generateSecurityCookieHash($user, $refreshToken);
	}

	private function generateSecurityCookieHash(UserInterface $user, RefreshTokenInterface $refreshToken): string
	{
		return sha1(
			sprintf('%s%s%s', $user->getId(), $refreshToken->getRefreshToken(), $this->securityCookieSalt)
		);
	}
}