<?php

namespace App\Service;

use DateTime;
use Gesdinet\JWTRefreshTokenBundle\Security\Authenticator\RefreshTokenAuthenticator;
use Gesdinet\JWTRefreshTokenBundle\Security\Provider\RefreshTokenProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Gesdinet\JWTRefreshTokenBundle\Request\RequestRefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

class RefreshTokenService
{
        /**
     * @var RefreshTokenManagerInterface
     */
    protected $refreshTokenManager;

    /**
     * @var int
     */
    protected $ttl;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var string
     */
    protected $userIdentityField;

    /**
     * @var string
     */
    protected $tokenParameterName;

    /**
     * @var bool
     */
    protected $singleUse;

    /**
     * @var RefreshTokenAuthenticator
     */
    protected $authenticator;

    /**
     * @var RefreshTokenProvider
     */
    protected $provider;

	/**
	 * AttachRefreshTokenOnSuccessListener constructor.
	 *
	 * @param RefreshTokenManagerInterface $refreshTokenManager
	 * @param int $ttl
	 * @param ValidatorInterface $validator
	 * @param RequestStack $requestStack
	 * @param string $userIdentityField
	 * @param string $tokenParameterName
	 * @param bool $singleUse
	 * @param RefreshTokenAuthenticator $authenticator
	 * @param RefreshTokenProvider $provider
	 */
    public function __construct( RefreshTokenManagerInterface $refreshTokenManager, $ttl, ValidatorInterface $validator, RequestStack $requestStack, $userIdentityField, $tokenParameterName, $singleUse, RefreshTokenAuthenticator $authenticator, RefreshTokenProvider $provider) {

        $this->refreshTokenManager = $refreshTokenManager;
        $this->ttl = $ttl;
        $this->validator = $validator;
        $this->provider = $provider;
        $this->authenticator = $authenticator;
        $this->requestStack = $requestStack;
        $this->userIdentityField = $userIdentityField;
        $this->tokenParameterName = $tokenParameterName;
        $this->singleUse = $singleUse;
    }

    public function create(UserInterface $user)
    {
        $request = $this->requestStack->getCurrentRequest();

        $refreshTokenString = RequestRefreshToken::getRefreshToken($request, $this->tokenParameterName);

        if ($refreshTokenString && true === $this->singleUse) {
            $refreshToken = $this->refreshTokenManager->get($refreshTokenString);
            $refreshTokenString = null;

            if ($refreshToken instanceof RefreshTokenInterface) {
                $this->refreshTokenManager->delete($refreshToken);
            }
        }

        if ($refreshTokenString) {

            return  $refreshTokenString;

        } else {

            $datetime = new DateTime();
            $datetime->modify('+'.$this->ttl.' seconds');

            $refreshToken = $this->refreshTokenManager->create();

            $accessor = new PropertyAccessor();
            $userIdentityFieldValue = $accessor->getValue($user, $this->userIdentityField);

            $refreshToken->setUsername($userIdentityFieldValue);
            $refreshToken->setRefreshToken();
            $refreshToken->setValid($datetime);

            $valid = false;

            while (false === $valid) {

                $valid = true;
                $errors = $this->validator->validate($refreshToken);

                if ($errors->count() > 0) {

                    foreach ($errors as $error) {

                        if ('refreshToken' === $error->getPropertyPath()) {

                            $valid = false;
                            $refreshToken->setRefreshToken();
                        }
                    }
                }
            }

            $this->refreshTokenManager->save($refreshToken);

            return $refreshToken->getRefreshToken();
        }
    }

	/**
	 * @param Request $request
	 * @return User|UserInterface|null
	 */
	public function getUserFromToken(Request $request)
	{
		return $this->authenticator->getUser($this->authenticator->getCredentials($request), $this->provider);
	}

	/**
	 * @param Request $request
	 * @return string
     */
	public function getToken(Request $request)
	{
		return RequestRefreshToken::getRefreshToken($request, $this->tokenParameterName);
	}
}