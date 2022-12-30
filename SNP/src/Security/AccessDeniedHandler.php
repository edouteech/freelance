<?php

namespace App\Security;

use App\Response\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
	public function handle(Request $request, AccessDeniedException $accessDeniedException)
	{
		$env = $_ENV['APP_ENV']??'prod';

		if( $env == 'dev' || $env == 'test')
			return new ApiResponse($accessDeniedException->getMessage(), [], Response::HTTP_FORBIDDEN);
		else
			return new ApiResponse('Access not allowed', [], Response::HTTP_FORBIDDEN);
	}
}