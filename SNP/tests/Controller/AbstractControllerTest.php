<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractControllerTest extends WebTestCase
{
	/**
	 * @param string $path
	 * @param array $params
	 * @param bool $connected
	 * @return Response
	 */
	public function post(string $path, array $params=[], $connected=true)
	{
		return $this->request('POST', $path, $params, $connected);
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param bool $connected
	 * @return Response
	 */
	public function get(string $path, array $params=[], $connected=true)
	{
		return $this->request('GET', $path, $params, $connected);
	}

	/**
	 * @param $method
	 * @param $path
	 * @param array $params
	 * @param bool $connected
	 * @return Response
	 */
	public function request($method, $path, $params=[], $connected=true){

		$client = static::createClient();
		$server = ['CONTENT_TYPE' => 'application/json'];

		if( isset($_ENV['AUTHORIZATION']) && $connected )
			$server['HTTP_AUTHORIZATION'] = $_ENV['AUTHORIZATION'];

		$client->request($method, $path, [],[], $server, json_encode($params));

		static::ensureKernelShutdown();

		return $client->getResponse();
	}
}