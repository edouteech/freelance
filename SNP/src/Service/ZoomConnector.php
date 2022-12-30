<?php

namespace App\Service;

use DateTime;
use Exception;

/**
 * Zoom connector Class
 */
class ZoomConnector extends AbstractService
{
	private $client;

	public static $registrantsCache = [];

	/**
	 * Zoom constructor.
	 * @param CurlClient $curlClient
	 */
	public function __construct(CurlClient $curlClient){

		$this->client = $curlClient;
		$this->client->setBaseUrl('https://api.zoom.us/v2');
		$this->client->setAuthorization('Bearer '.$_ENV['ZOOM_TOKEN']);
	}

	/**
	 * @param $userId
	 * @param $params
	 * @param array $settings
	 * @return array|bool
	 * @throws Exception
	 */
	public function createWebinar($userId, $params, $settings=[]){

		$params = array_merge([
			'topic'=>'Formation',
			'type'=>5,
			'duration'=>60,
			'timezone'=>'Europe/Paris',
			'agenda'=>'',
			'start_time'=> '+1 hour'
		], $params);

		$params['start_time'] = date('c', is_string($params['start_time'])?strtotime($params['start_time']):$params['start_time']);

		if( is_array($settings['alternative_hosts']??false) )
			$settings['alternative_hosts'] = implode(',', $settings['alternative_hosts']);

		$params['settings'] = array_merge([
			'approval_type'=>1,
			'host_video'=>true,
			'audio'=>'voip',
			'panelists_video'=>true,
			'registration_type'=>1,
			'show_share_button'=>false,
			'allow_multiple_devices'=>false,
			'close_registration'=>true,
			'meeting_authentication'=>false,
			'global_dial_in_countries'=>[],
			'contact_name'=>$_ENV['ZOOM_DEFAULT_CONTACT_NAME'],
			'contact_email'=>$_ENV['ZOOM_DEFAULT_CONTACT_EMAIL'],
			'registrants_email_notification'=>false
		], $settings);

		return $this->client->post('/users/'.$userId.'/webinars', $params);
	}

	/**
	 * @return array|bool
	 * @throws Exception
	 */
	public function listUsers(){

		$response = $this->client->get('/users');
		return $response['users']??[];
	}

	/**
	 * @param $email
	 * @return array|bool
	 * @throws Exception
	 */
	public function getUser($email){

		$users = $this->listUsers();

		foreach ($users as $user){

			if( $user['email'] == $email ){

				$user['password'] = 'Formation'.strtolower(str_replace(' ', '', $user['first_name']));
				return $user;
			}
		}

		return false;
	}

	/**
	 * @param $webinarId
	 * @param $params
	 * @param array $settings
	 * @return array|bool
	 * @throws Exception
	 */
	public function updateWebinar($webinarId, $params, $settings=[]){

		$params['settings'] = $settings;

		return $this->client->patch('/webinars/'.$webinarId, $params);
	}

	/**
	 * @param $webinarId
	 * @return array|bool
	 * @throws Exception
	 */
	public function getWebinarParticipantsReport($webinarId){

		$instances = $this->listPastWebinarInstances($webinarId);
		$participants = [];

		foreach ($instances as $instance ){

			$uuid = $instance['uuid'];

			if( strpos($uuid, '/') !== false )
				$uuid = urlencode(urlencode($uuid));

			$result = $this->client->get('/report/webinars/'.$uuid.'/participants', ['page_size'=>300]);
			$participants = array_merge($participants, $result['participants']??[]);
		}

		$data = [];

		foreach ($participants as $participant){

			if( !$session = $data[$participant['user_email']]??false ){

				$session = [
					'id' => $participant['id'],
					'name' => $participant['name'],
					'email' => $participant['user_email'],
					'join_time' => '',
					'leave_time' => '',
					'duration' => 0,
					'attentiveness_score' => 0,
					'raw_log' => []
				];
			}

			$session['attentiveness_score'] = max(intval($participant['attentiveness_score']), intval($session['attentiveness_score']));

			$sessionJoinTime = new DateTime($session['join_time']);
			$participantJoinTime = new DateTime($participant['join_time']);

			$sessionLeaveTime = new DateTime($session['leave_time']);
			$participantLeaveTime = new DateTime($participant['leave_time']);

			if( empty($session['join_time']) )
				$session['join_time'] = $participantJoinTime->format('c');
			else
				$session['join_time'] = $sessionJoinTime > $participantJoinTime ? $participantJoinTime->format('c') : $sessionJoinTime->format('c');

			if( empty($session['leave_time']) )
				$session['leave_time'] = $participantLeaveTime->format('c');
			else
				$session['leave_time'] = $sessionLeaveTime < $participantLeaveTime ? $participantLeaveTime->format('c') : $sessionLeaveTime->format('c');

			$participantDuration = round(($participantLeaveTime->getTimestamp() - $participantJoinTime->getTimestamp()) / 60);
			$session['duration'] += $participantDuration;

			$session['raw_log'][] = ['join_time'=>$participantJoinTime->format('c'), 'leave_time'=>$participantLeaveTime->format('c'), 'duration'=>$participantDuration];

			$data[$participant['user_email']] = $session;
		}

		return $data;
	}

	/**
	 * @param $webinarId
	 * @return array|bool
	 * @throws Exception
	 */
	public function getWebinarRegistrants($webinarId){

        $data = [];

        if( !$webinarId )
            return $data;

        if( isset(self::$registrantsCache[$webinarId]) )
	        return self::$registrantsCache[$webinarId];

		$result = $this->client->get('/webinars/'.$webinarId.'/registrants', ['page_size'=>300]);
		$registrants = $result['registrants']??[];

		foreach ($registrants as $registrant)
			$data[$registrant['email']] = $registrant;

		$result = $this->client->get('/webinars/'.$webinarId.'/panelists', ['page_size'=>300]);
		$panelists = $result['panelists']??[];

		foreach ($panelists as $panelist)
			$data[$panelist['email']] = $panelist;

        self::$registrantsCache[$webinarId] = $data;

		return $data;
	}

	/**
	 * @param $webinarId
	 * @return array|bool
	 * @throws Exception
	 */
	public function getWebinarPollResults($webinarId){

		$data = [];
		$result = $this->client->get('/report/webinars/'.$webinarId.'/polls');

		if( $result['questions']??false ){

			foreach ($result['questions'] as $question)
				$data[$question['email']] = $question['question_details'];
		}

		return $data;
	}

	/**
	 * @param $webinarId
	 * @return array|bool
	 * @throws Exception
	 */
	public function listPastWebinarInstances($webinarId){

		$result = $this->client->get('/past_webinars/'.$webinarId.'/instances');

		return $result['webinars']??[];
	}

	/**
	 * @param $webinarId
	 * @param $participantEmail
	 * @return array|bool
	 * @throws Exception
	 */
	public function getWebinarParticipantReport($webinarId, $participantEmail){

		$participants = $this->getWebinarParticipantsReport($webinarId);

		return $participants[$participantEmail]??false;
	}

	/**
	 * @param $webinarId
	 * @return array|bool
	 * @throws Exception
	 */
	public function getWebinarPolls($webinarId){

		$result = $this->client->get('/report/webinars/'.$webinarId.'/polls', ['page_size'=>300]);
		return $result['questions']??[];
	}

	/**
	 * @param $webinarId
	 * @param $participantEmail
	 * @return array|bool
	 * @throws Exception
	 */
	public function getWebinarPoll($webinarId, $participantEmail){

		$participants = $this->getWebinarPolls($webinarId);
		foreach ($participants as $participant){

			if( $participant['email'] == $participantEmail )
				return $participant;
		}

		return false;
	}

	/**
	 * @param int $webinarId
	 * @param $occurrence_id
	 * @param array $params
	 * @return array|bool
	 * @throws Exception
	 */
	public function addWebinarRegistrant($webinarId, $occurrence_id, $params){

		$url = '/webinars/'.$webinarId.'/registrants';

		if( $occurrence_id )
			$url .= '?occurrence_ids='.$occurrence_id;

		$registrant = $this->client->post($url, $params);

		if( isset(self::$registrantsCache[$webinarId]) )
			self::$registrantsCache[$webinarId][$params['email']] = $registrant;

		return $registrant;
	}

	/**
	 * @param int $webinarId
	 * @param array $panelists
	 * @return array|bool
	 * @throws Exception
	 */
	public function addWebinarPanelists($webinarId, $panelists){

		if( empty($panelists) )
			return [];

		return $this->client->post('/webinars/'.$webinarId.'/panelists', ['panelists'=>$panelists]);
	}

	/**
	 * @param int $webinarId
	 * @param array $panelist
	 * @return array|bool
	 * @throws Exception
	 */
	public function addWebinarPanelist($webinarId, $panelist){

		return $this->addWebinarPanelists($webinarId, [$panelist]);
	}

	/**
	 * @param int $webinarId
	 * @return array|bool
	 * @throws Exception
	 */
	public function getWebinarPanelists($webinarId){

		$result = $this->client->get('/webinars/'.$webinarId.'/panelists');

		$panelists = $result['panelists']??[];

		$data = [];

		foreach ($panelists as $panelist)
			$data[$panelist['email']] = $panelist;

		return $data;
	}

	/**
	 * @param int $webinarId
	 * @param $occurrence_id
	 * @param $action
	 * @param $registrants
	 * @return array|bool
	 * @throws Exception
	 */
	public function updateWebinarRegistrantsStatus($webinarId, $occurrence_id, $action, $registrants){

		$params = [
			'action'=> $action,
			'registrants'=> $registrants
		];

		$url = '/webinars/'.$webinarId.'/registrants/status';

		if( $occurrence_id )
			$url.= '?occurrence_id='.$occurrence_id;

		return 	$this->client->put($url, $params);
	}

	/**
	 * @param int $webinarId
	 * @param string $title
	 * @param array $questions
	 * @return array|bool
	 * @throws Exception
	 */
	public function createWebinarPoll($webinarId, $title, $questions){

		return $this->client->post('/webinars/'.$webinarId.'/polls', [
			'title'=>$title,
			'questions'=>$questions
		]);
	}
}
