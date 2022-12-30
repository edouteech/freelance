<?php

namespace App\Logger;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserRequestProcessor
{
	private $tokenStorage;
	private $requestStack;

	public function __construct(TokenStorageInterface $tokenStorage, RequestStack $requestStack)
	{
		$this->tokenStorage = $tokenStorage;
		$this->requestStack = $requestStack;
	}

	public function __invoke(array $record)
	{
		if (!$token = $this->tokenStorage->getToken())
			return $record;

		$record['extra']['user'] = defined('CURRENT_USER_ID')?CURRENT_USER_ID:false;

		if( $currentRequest = $this->requestStack->getCurrentRequest() ){

			$record['extra']['method'] = $currentRequest->getMethod();
			$record['extra']['path'] = $currentRequest->getPathInfo();
		}

		return $record;
	}
}