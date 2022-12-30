<?php

namespace App\Service;

use Exception;

/**
 * Class Ping
 */
class Ping extends AbstractService
{
	private $client;

	/**
	 * Ping constructor.
	 * @param CurlClient $curlClient
	 */
	public function __construct(CurlClient $curlClient)
	{
		$this->client = $curlClient;
		$this->client->setBaseUrl('https://hc-ping.com/');
	}

	/**
	 * @param $id
	 * @return array|bool
	 * @throws Exception
	 */
	public function ping($id)
	{
		return $this->client->get($id);
	}
}