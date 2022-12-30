<?php

namespace App\Service;

use Exception;
use App\Entity\Company;

use Cocur\Slugify\Slugify;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;

class SnpiConnector extends AbstractService {

	private $logger;
	private $client;
	private $cache;

	/**
	 * Eudonet constructor.
	 * @param LoggerInterface $logger
	 * @param CurlClient $curlClient
	 * @param CacheInterface $cache
	 */
	public function __construct(
		LoggerInterface $logger,
		CurlClient $curlClient,
		CacheInterface $cache
	) {

		$this->logger = $logger;
		$this->client = $curlClient;
		$this->cache = $cache;
		
		$this->client->setBaseUrl($_ENV['SNPI_OLD_API_URL']);
		$this->client->setErrorKey('error_string');
	}

	/**
	 * @param $params
	 * @return array|mixed
	 * @throws Exception
	 */
	public function list($params)
	{
		if( !intval($_ENV['SIGNATURES_ENABLED']??0) )
			return ['items'=>[], 'count'=>0];

		$output = $this->client->get('/universign/compte.php', $params);
		$output = $this->handleResponse($output);

        return [
            'items' => $output['collects'],
            'count' => intval($output['stats']['total_sign'])
        ];
    }

    /**
	 * @param $params
	 * @return array|mixed
	 * @throws Exception
	 */
	public function getStock($params)
	{
		$params = array_merge($params, ['stats'=>1]);

		$output = $this->client->get('/universign/compte.php', $params);
		$output = $this->handleResponse($output);

		return intval($output['stock_left'])??0;
	}

    /**
	 * @param $params
	 * @return array|mixed
	 * @throws Exception
	 */
	public function updateStock($params)
	{
		$output = $this->client->post('/universign/stock.php', $params);
		$output = $this->handleResponse($output);

		return intval($output['stock_left'])??0;
	}

	/**
	 * @param $params
	 * @return array|mixed
	 * @throws Exception
	 */
	public function cancel($params)
	{
		$output = $this->client->get('/universign/cancel.php', $params);

		return $this->handleResponse($output);
	}

	/**
	 * @param $params
	 * @return array|mixed
	 * @throws Exception
	 */
	public function create($params)
	{
		set_time_limit(180);

		$output = $this->client->postForm('/universign/create.php', $params);

		return $this->handleResponse($output);
	}

	/**
	 * @param $params
	 * @return array|mixed
	 * @throws Exception
	 */
	public function checkFile($params)
	{
		$output = $this->client->postForm('/universign/doc_verif.php', $params);

		return $this->handleResponse($output);
	}

	/**
	 * @param $params
	 * @return array|mixed
	 * @throws Exception
	 */
	public function getDownloadUrl($params)
	{
		return $this->client->getUrl('/universign/download.php', $params);
	}

	/**
	 * @param $params
	 * @return array|mixed
	 * @throws Exception
	 */
	public function refresh($params)
	{
		$output = $this->client->get('/universign/refresh.php', $params);

		return $this->handleResponse($output);
	}

	/**
	 * @return array|mixed
	 * @throws Exception
	 */
	public function getPacks()
	{
		$output = $this->client->get('/universign/getpack.php');

		return $this->handleResponse($output);
	}

	/**
	 * @param $params
	 * @return array|mixed
	 * @throws Exception
	 */
	public function validateOrder($params)
	{
		$output = $this->client->post('/universign/validate_order.php', $params);

		return $this->handleResponse($output);
	}

	/**
	 * @param $params
	 * @return array|mixed
	 * @throws Exception
	 */
	public function resendLink($params)
	{
		$output = $this->client->post('/universign/resend_link.php', $params);

		return $this->handleResponse($output);
	}

    /**
     * @param $params
     * @return array|mixed
     * @throws Exception
     */
    public function getRealEstateData(Company $company)
    {
		return $this->cache->get(
			md5('real_estate_data_for_company_' . $company->getId()),
			function (CacheItem $cacheItem) use ($company) {
				$cacheItem->expiresAfter(3600 * 24); // expire after 1 day

				$data = null;
				$slugify = new Slugify();
				$client = HttpClient::create();
				$response = $client->request(
					'GET',
					sprintf('https://api.apimo.pro/user/%s?with=generate_token', $company->getMemberId()),
					[
						'auth_basic' => ['76', '199c3d770911b5c9d3ae4417dfaa57c053cda11a'],
						'headers' => [
							'Content-Type' => 'application/json',
						]
					]
				);

				if ($response->getStatusCode() == Response::HTTP_OK) {
					$data = $response->toArray();
				}

				return [
					'url' => sprintf(
						"https://www.snpi.fr/agence/%d/%s/%s-%s",
						$company->getId(),
						$slugify->slugify($company->getName()),
						$slugify->slugify($company->getZip()),
						$slugify->slugify($company->getCity())
					),
					'url_apimo' => $data ? $data['autologin']['url'] : false
				];
			}
		);
    }

	/**
	 * @param $output
	 * @return mixed
	 * @throws Exception
	 */
	private function handleResponse($output){

		if( trim($output['status']??'') != 'ok' ){

			if( is_array($output['error_string']??'') )
				throw new Exception($output['error_string']['message']??'Error');
			else
				throw new Exception($output['error_string']??'Error');
		}

		return $output['data']??true;
	}
}