<?php

namespace App\Service;

use Exception;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Throwable;

class CurlClient {

	private $client;
	private $baseUrl;
	private $authorization;
	private $logger;
	private $contentType;
	private $errorKey;

	/**
	 * Api Connector constructor.
	 * @param LoggerInterface $logger
	 */
	public function __construct(LoggerInterface $logger){

		$this->client = new CurlHttpClient(['verify_peer'=>($_ENV['APP_ENV']??'prod'!='dev')]);

		$this->logger = $logger;
		$this->errorKey = 'message';
		$this->contentType = 'application/json';
	}

	/**
	 * @param $baseUrl
	 */
	public function setBaseUrl($baseUrl){

		$this->baseUrl = $baseUrl;
	}

	/**
	 * @param $key
	 */
	public function setErrorKey($key){

		$this->errorKey = $key;
	}

	/**
	 * @param $contentType
	 */
	public function setContentType($contentType){

		$this->contentType = $contentType;
	}

	/**
	 * @param $authorization
	 */
	public function setAuthorization($authorization){

		$this->authorization = $authorization;
	}

	/**
	 * @param $url
	 * @param array $params
	 * @return string
	 */
	public function getUrl($url, $params=[]){

		return $this->baseUrl.$url.(!empty($params)?'?'.http_build_query($params):'');
	}

	/**
	 * @param array $headers
	 * @return array
	 */
	private function getHeaders($headers=[]){

		if( is_object($headers) && $headers instanceof Headers ){

			if( !empty($this->authorization) )
				$headers->addTextHeader('Authorization', $this->authorization);

			return $headers->toArray();
		}
		else{

			if( !($headers['Content-Type']??false) && !empty($this->contentType) )
				$headers['Content-Type'] = $this->contentType;

			if(  !($headers['Authorization']??false) && !empty($this->authorization) )
				$headers['Authorization'] = $this->authorization;

			return $headers;
		}
	}

	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @return array|bool
	 * @throws Exception
	 */
	public function get($path='', $params=[], $headers=[]){

		try {
			return $this->request('GET', $path, $params, $headers);
		}
		catch (Throwable $t) {
			throw new Exception($t->getMessage(), $t->getCode());
		}
	}


	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @return array|bool
	 * @throws Exception
	 */
	public function post($path='', $params=[], $headers=[]){

		try{
			return $this->request('POST', $path, $params, $headers);
		}
		catch (Throwable $t) {

			throw new Exception($t->getMessage(), $t->getCode());
		}
	}


	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @return array|bool
	 * @throws Exception
	 */
	public function put($path='', $params=[], $headers=[]){

		try{
			return $this->request('PUT', $path, $params, $headers);
		}
		catch (Throwable $t) {

			throw new Exception($t->getMessage(), $t->getCode());
		}
	}


	/**
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @return array|bool
	 * @throws Exception
	 */
	public function patch($path='', $params=[], $headers=[]){

		try {
			return $this->request('PATCH', $path, $params, $headers);
		}
		catch (Throwable $t) {
			throw new Exception($t->getMessage(), $t->getCode());
		}
	}

	/**
	 * @param $method
	 * @param string $path
	 * @param array $params
	 * @param array $headers
	 * @return array|bool|SimpleXMLElement
	 * @throws ClientExceptionInterface
	 * @throws DecodingExceptionInterface
	 * @throws RedirectionExceptionInterface
	 * @throws ServerExceptionInterface
	 * @throws TransportExceptionInterface
	 * @throws Exception
	 */
	public function request($method, $path='', $params=[], $headers=[]){

		if( ($headers['Content-Type']??false) == 'multipart/form-data'){

			$formData = new FormDataPart($params);

			$headers = $formData->getPreparedHeaders();
			$params = $formData->bodyToIterable();
		}

		$requestHeaders = $this->getHeaders($headers);

		$options = ['headers' => $requestHeaders];

		if( $method == 'GET' ){

			$options['query'] = $params;
		}
		else{

			if( ($requestHeaders['Content-Type']??'') == 'application/json' )
				$options['json'] = $params;
			else
				$options['body'] = $params;
		}

		$this->logger->debug($this->baseUrl.$path.' '.json_encode($options));

		$response = $this->client->request($method, $this->baseUrl.$path, $options);

		$statusCode = $response->getStatusCode();
		$responseHeaders = $response->getHeaders(false);

		$responseContentType = trim(strtolower($responseHeaders['content-type'][0]??'application/json'));

		if( strpos($responseContentType, 'json') !== false ){

			$value = $response->getContent(false);
			$value = !empty($value) ? $response->toArray(false) : '';

			if( $statusCode >= 200 && $statusCode < 300)
				return $value;

			$error = $value[$this->errorKey]??'Service error';
			if( is_array($error) ) $error = json_encode($error);

			throw new Exception($error, $statusCode);

		}
		elseif( strpos($responseContentType, 'xml') !== false ){

			$value = $response->getContent(false);

			if( !empty($value) )
				$value = @simplexml_load_string($value);

			if( $statusCode >= 200 && $statusCode < 300)
				return $value;

			$errorKey = $this->errorKey;
			$error = $value->$errorKey??'Service error';

			if( is_array($error) ) $error = json_encode($error);

			throw new Exception($error, $statusCode);
		}
		else{

			$value = $response->getContent(false);

			if( $statusCode >= 200 && $statusCode < 300)
				return $value;

			throw new Exception('Service error', $statusCode);
		}
	}

	/**
	 * @param string $path
	 * @param array $formFields
	 * @return array|bool
	 * @throws Exception
	 */
	public function postForm($path='', $formFields=[]){

		try {

			return $this->request('POST', $path, $formFields, ['Content-Type'=>'multipart/form-data']);
		}
		catch (Throwable $t) {

			throw new Exception($t->getMessage(), $t->getCode());
		}
	}
}