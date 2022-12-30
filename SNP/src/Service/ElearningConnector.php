<?php

namespace App\Service;

use App\Entity\Contact;
use App\Entity\FormationCourse;
use Exception;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class ElearningConnector extends AbstractService {

	private $client;
	private $mailer;

	/**
	 * E-learning Connector constructor.
	 * @param Mailer $mailer
	 * @param CurlClient $curlClient
	 */
	public function __construct(Mailer $mailer, CurlClient $curlClient){

		$this->mailer = $mailer;
		$this->client = $curlClient;

		$this->client->setBaseUrl($_ENV['E_LEARNING_URL']);
		$this->client->setContentType('');
	}

	/**
	 * @param $user
	 * @return array|bool
	 * @throws Exception
	 */
	public function createUser($user){

		//todo: better cryptor
		$token = base64_encode(random_bytes(50));
		$token = substr(preg_replace('/[^A-Za-z0-9\-]/', '', $token), 0, 50);

		if( !isset($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL) )
			throw new Exception('Invalid email');

		if( !isset($user['firstname']) || strlen($user['firstname'] ) < 2 )
			throw new Exception('Invalid firstname');

		if( !isset($user['lastname']) || strlen($user['lastname'] ) < 2 )
			throw new Exception('Invalid lastname');

		return $this->post('/api/users/create', [
			'token' => $token,
			'mail' => $user['email'],
			'nom' => $user['lastname'],
			'prenom' => $user['firstname']
		]);
	}

	/**
	 * @param Contact $contact
	 * @param FormationCourse $formationCourse
	 * @return void
	 * @throws Exception
	 * @throws ExceptionInterface
	 */
	public function registerUser(FormationCourse $formationCourse, Contact $contact){

		$formation = $formationCourse->getFormation();

		if( strlen($contact->getElearningToken()) < 2 )
			throw new Exception('E-learning: Invalid token');

		if( strlen($formation->getCode()) < 2 )
			throw new Exception('E-learning: Invalid code');

		$this->post('/api/users/register', [
			'token' => $contact->getElearningToken(),
			'code_theme' => $formation->getCode()
		]);

		if( $email = $contact->getElearningEmail() ){

			$bodyMail = $this->mailer->createBodyMail('e-learning/registered.html.twig', ['title'=>"Confirmation d'inscription - À distance", 'contact' => $contact, 'formation'=>$formation]);
			$this->mailer->sendMessage($email, "Confirmation d'inscription - À distance", $bodyMail);
		}
	}

	/**
	 * @param $token
	 * @param $data
	 * @return array|bool
	 * @throws Exception
	 */
	public function updateUser($token, $data){

		if( strlen($token) < 2 )
			throw new Exception('E-learning: Invalid token');

		$params = array_merge([
			'token'=>$token
		], $data);

		return $this->post('/api/users/update', $params);
	}


	/**
	 * @param $path
	 * @param array $params
	 * @return array|bool
	 * @throws Exception
	 */
	public function post($path='', $params=[]){

		$value = $this->client->post($path, $params);

		if( !$value['status']??false )
			throw new Exception($value['message']??'E-learning Internal server error');

		return $value;
	}
}
