<?php

namespace App\Service;

use Exception;

use Psr\Log\LoggerInterface;

class ContraliaConnector extends AbstractService {

	private $client;
	private $logger;
	private $offerCode;

	/**
	 * Contralia constructor.
	 * https://www.contralia.fr/Contralia/
	 *
	 * @param LoggerInterface $logger
	 * @param CurlClient $curlClient
	 */
	public function __construct(LoggerInterface $logger, CurlClient $curlClient){

		$this->logger = $logger;
		$this->client = $curlClient;

		$this->client->setBaseUrl('https://www.contralia.fr:443/Contralia/api/v2');
		$this->client->setContentType('');
	}

	/**
	 *
	 * @param $offerCode
	 * @throws Exception
	 */
	public function setAccount($offerCode){

		$this->offerCode = $offerCode;

		if( !$_ENV['CONTRALIA_'.$offerCode.'_ID']??false )
			throw new Exception('CONTRALIA_'.$offerCode.'_ID is empty');

		if( !$_ENV['CONTRALIA_'.$offerCode.'_PASSWORD']??false )
			throw new Exception('CONTRALIA_'.$offerCode.'_PASSWORD is empty');

		$this->client->setAuthorization('Basic '.base64_encode($_ENV['CONTRALIA_'.$offerCode.'_ID'].':'.$_ENV['CONTRALIA_'.$offerCode.'_PASSWORD']));
	}

	public function getAccount(){

		return $this->offerCode;
	}

    /**
     * @param array $input
     * @return string
     * @throws Exception https://www.contralia.fr/Contralia/doc/corev2%24transaction/initiate
     */
	public function initiate(array $input){

		$input['offerCode'] = $this->getAccount();

		$params = $this->format($input, [
			'organizationalUnitCode'=>['type'=>'string', 'required'=>true, 'default'=>'', 'max'=>255],
			'customRef'=>['type'=>'string', 'required'=>true, 'default'=>'', 'max'=>32],
			'keywords'=>['type'=>'string', 'required'=>false, 'default'=>'', 'max'=>1000],
			'signatoriesCount'=>['type'=>'int', 'required'=>false, 'default'=>1],
			'profileNumber'=>['type'=>'int', 'required'=>false, 'default'=>1, 'min'=>1, 'max'=>3],
			'parentTransactionId'=>['type'=>'string', 'required'=>false, 'default'=>'', 'max'=>32],
			'deferredTimeout'=>['type'=>'bool', 'required'=>false, 'default'=>false],
			'testMode'=>['type'=>'bool', 'required'=>false]
		]);

		$response = $this->client->post('/'.$this->getAccount().'/transactions', $params);

		if( !isset($response['id']) )
			throw new Exception('Transaction id is not defined');

		return (string)$response['id'];
	}

    /**
     * @param string $transactionId
     * @param int $position
     * @param array $input
     * @return string
     *
     * @throws Exception https://www.contralia.fr/Contralia/doc/corev2%24transaction/signatory
     */
	public function addSignatory(string $transactionId, int $position, array $input){

		$input['offerCode'] = $this->getAccount();

		$params = $this->format($input, [
			'offerCode'=>['type'=>'string', 'required'=>true, 'default'=>'', 'max'=>255],
			'fieldNumber'=>['type'=>'int'],
			'profileNumber'=>['type'=>'int', 'default'=>1, 'min'=>1, 'max'=>3],
			'identityId'=>['type'=>'int', 'required'=>false],
			'signatureLevel'=>['type'=>'enum', 'allowed'=>['SIMPLE_LCP','ADVANCED_LCP','ADVANCED_NCP','ADVANCED_QCP']],
			'civility'=>['type'=>'bool', 'required'=>false],
			'firstname'=>['type'=>'string', 'required'=>true, 'max'=>50],
			'lastname'=>['type'=>'string', 'required'=>true, 'max'=>50],
			'email'=>['type'=>'email', 'required'=>false, 'max'=>200],
			'phone'=>['type'=>'string', 'required'=>false],
			'address.street'=>['type'=>'string', 'required'=>false, 'max'=>200],
			'address.complements'=>['type'=>'string', 'required'=>false, 'max'=>200],
			'address.postalCode'=>['type'=>'string', 'required'=>false, 'max'=>5],
			'address.city'=>['type'=>'string', 'required'=>false, 'max'=>200],
			'address.country'=>['type'=>'string', 'required'=>false, 'max'=>200],
			'companyName'=>['type'=>'string', 'required'=>false, 'max'=>200],
			'companyRegistrationNumber'=>['type'=>'string', 'required'=>false, 'max'=>50],
			'entity'=>['type'=>'string', 'required'=>false, 'max'=>200],
			'role'=>['type'=>'string', 'required'=>false, 'max'=>200],
			'certCountryCode'=>['type'=>'string', 'required'=>false, 'max'=>2],
			'certOrgId'=>['type'=>'string', 'required'=>false, 'max'=>100],
			'signatureType'=>['type'=>'enum', 'allowed'=>['OTP','PAD','TOKEN','CONSENT_PROOF','CONSENT','IDENTITY'], 'default'=>'OTP'],
			'withOtp'=>['type'=>'bool', 'default'=>true],
			'consentModes'=>['type'=>'string', 'required'=>false, 'max'=>255],
			'electronicSignature'=>['type'=>'bool', 'default'=>true]
		]);

		$response = $this->client->post('/transactions/'.$transactionId.'/signatory/'.$position, $params);

		if( !isset($response['id']) )
			throw new Exception('Transaction id is not defined');

		return (string)$response['id'];
	}

    /**
     * @param string $transactionId
     * @param array $input
     * @return string
     * @throws Exception https://www.contralia.fr/Contralia/doc/corev2$transaction/upload
     */
	public function upload(string $transactionId, array $input){

		$params = $this->format($input, [
			'file'=>['type'=>'multipart/form-data', 'required'=>true, 'default'=>''],
			'name'=>['type'=>'string', 'required'=>false, 'max'=>200],
			'hash'=>['type'=>'string', 'required'=>false, 'max'=>64],
			'alias '=>['type'=>'string', 'required'=>false, 'max'=>200],
			'rank'=>['type'=>'int', 'required'=>false, 'min'=>1, 'max'=>3],
			'keywords'=>['type'=>'string', 'required'=>false, 'max'=>1000],
			'withoutProcessing'=>['type'=>'bool', 'required'=>false, 'default'=>false],
			'archive'=>['type'=>'bool', 'required'=>false, 'default'=>true],
			'signatureFormat'=>['type'=>'enum', 'required'=>false, 'allowed'=>['PADES','XADES','CADES']],
			'fields'=>['type'=>'array', 'required'=>false]
		]);

		$signatorySignature = $signatoryImage = 1;
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="no"?><fields xmlns="http://www.contralia.fr/champsPdf">';

		if( isset($params['fields']) && is_array($params['fields']) ){

			foreach ($params['fields'] as $fields){

				$xml .= '<'.$fields['type'];

				if( $fields['type'] == 'signatorySignature' )
					$xml .= ' number="'.$signatorySignature.'"';
				elseif( $fields['type'] == 'signatoryImage' )
					$xml .= ' number="'.$signatoryImage.'"';

				$xml .= '><box';

				foreach ($fields['settings'] as $key=>$value)
					$xml.= ' '.$key.'="'.$value.'"';

				$xml .= '/></'.$fields['type'].'>';

				if( $fields['type'] == 'signatorySignature' )
					$signatorySignature++;
			}
			$xml .= '</fields>';

			$params['fields'] = $xml;
		}

		$response = $this->client->post('/transactions/'.$transactionId.'/document', $params, ['Content-Type'=>'multipart/form-data']);

		if( !isset($response['name']) )
			throw new Exception('File name is not defined');

		return (string)$response['name'];
	}

    /**
     * @param string $transactionId
     * @return array|bool
     * @throws Exception https://www.contralia.fr/Contralia/doc/corev2%24transaction/terminate
     */
	public function terminate(string $transactionId){

		$response = $this->client->post('/transactions/'.$transactionId.'/terminate');

		if( !isset($response['id']) )
			throw new Exception('Transaction id is not defined');

		return (string)$response['id'];
	}

    /**
     * @param string $transactionId
     * @param array $input
     * @return array|bool
     * @throws Exception https://www.contralia.fr/Contralia/doc/corev2%24transaction/status
     */
	public function getStatus(string $transactionId, array $input){

		$params = $this->format($input, [
			'includeChildTransactions'=>['type'=>'bool'],
			'format'=>['type'=>'enum', 'allowed'=>['json','xml'], 'default'=>'xml']
		]);

		return $this->client->get('/transactions/'.$transactionId, $params);
	}

    /**
     * @param string $transactionId
     * @param array $input
     * @return array|bool
     * @throws Exception https://www.contralia.fr/Contralia/doc/corev2%24transaction/currentDoc
     */
	public function getCurrentDoc(string $transactionId, array $input){

		$params = $this->format($input, [
			'name'=>['type'=>'string', 'max'=>200],
			'naming'=>['type'=>'enum', 'allowed'=>['LEGACY','SIMPLE','FULL'], 'default'=>'LEGACY'],
			'contentDisposition '=>['type'=>'enum', 'allowed'=>['attachment','inline'], 'default'=>'attachment']
		]);

		return $this->client->get('/transactions/'.$transactionId.'/currentDoc', $params);
	}

    /**
     * @param string $transactionId
     * @param array $input
     * @return array|bool
     * @throws Exception https://www.contralia.fr/Contralia/doc/corev2%24transaction/finalDoc
     */
	public function getFinalDoc(string $transactionId, array $input=[]){

		$params = $this->format($input, [
			'name'=>['type'=>'string', 'max'=>200],
			'naming'=>['type'=>'enum', 'allowed'=>['LEGACY','SIMPLE','FULL'], 'default'=>'LEGACY'],
			'contentDisposition '=>['type'=>'enum', 'allowed'=>['attachment','inline'], 'default'=>'attachment']
		]);

		return $this->client->get('/transactions/'.$transactionId.'/finalDoc', $params);
	}

    /**
     * @param string $signatureId
     * @param array $input
     * @return array|bool
     * @throws Exception https://www.contralia.fr/Contralia/doc/corev2%24signature/genOtp
     */
	public function genOtp(string $signatureId, array $input){

		$params = $this->format($input, [
			'deliveryMode'=>['type'=>'enum', 'allowed'=>['EMAIL','SMS','VOICE','AUTO'], 'default'=>'AUTO'],
			'phone'=>['type'=>'string'],
			'email'=>['type'=>'email'],
			'customSender'=>['type'=>'string', 'max'=>11, 'min'=>3],
			'customSubject'=>['type'=>'string'],
			'customMessage'=>['type'=>'string'],
			'messageLocale'=>['type'=>'enum', 'allowed'=>['en','fr']],
			'test'=>['type'=>'bool']
		]);

		$response = $this->client->post('/signatures/'.$signatureId.'/genOtp', $params);

		if( !isset($response['id']) )
			throw new Exception('Transaction id is not defined');

		return (int)$response['sentCount'];
	}

    /**
     * @param string $signatureId
     * @param array $input
     * @return array|bool
     * @throws Exception https://www.contralia.fr/Contralia/doc/corev2%24signature/checkOtp
     */
	public function checkOtp(string $signatureId, array $input){

		$params = $this->format($input, [
			'otp'=>['type'=>'string', 'required'=>true, 'max'=>6]
		]);

		$response = $this->client->post('/signatures/'.$signatureId.'/checkOtp', $params);

		if( !isset($response['id']) )
			throw new Exception('Transaction id is not defined');

		return (string)$response['id'];
	}

    /**
     * @param string $signatureId
     * @param array $input
     * @return array|bool
     * @throws Exception https://www.contralia.fr/Contralia/doc/corev2%24signature/checkOtp
     */
	public function sign(string $signatureId, array $input){

		$params = $this->format($input, [
			'otp'=>['type'=>'string', 'required'=>true, 'max'=>6],
			'agreementText'=>['type'=>'string'],
			'identityAuthToken'=>['type'=>'string']
		]);

		$response = $this->client->post('/signatures/'.$signatureId.'/otp', $params);

		if( !isset($response['id']) )
			throw new Exception('Transaction id is not defined');

		return (string)$response['id'];
	}
}