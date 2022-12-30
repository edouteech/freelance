<?php

namespace App\Service;

use Exception;
use WonderPush\Errors\Base;
use WonderPush\Obj\Notification;
use WonderPush\Obj\NotificationAlert;
use WonderPush\Obj\NotificationAlertAndroid;
use WonderPush\Obj\NotificationAlertIos;
use WonderPush\Obj\NotificationAlertWeb;
use WonderPush\Params\DeliveriesCreateParams;
use WonderPush\WonderPush;

/**
 * Wonderpush connector Class
 */
class WonderpushConnector extends AbstractService
{
	private $wonderpush;

	public function __construct(){

		$this->wonderpush = new WonderPush($_ENV['WONDERPUSH_ACCESS_TOKEN'], $_ENV['WONDERPUSH_APPLICATION_ID']);
	}

	/**
	 * @throws Exception
	 */
	public function getSegmentByTag($tag){

		$segments = $this->getSegments();

		foreach ($segments as $segment){

			$filters = $segment['query']['filters'];

			if( $filters['match'] == "all" ){

				foreach ($filters['filters'] as $filter){

					if( $filter['fieldName'] == 'tags' && $filter['operatorSymbol'] == 's_in' && in_array($tag, $filter['operandValues']))
						return $segment;
				}
			}
		}

		return false;
	}

	/**
	 * @throws Exception
	 */
	public function getSegments(){

		$response = $this->wonderpush->rest()->get('/segments');

		return $response['data']??[];
	}

	/**
	 * @throws Exception
	 */
	public function createDelivery($params, $target='@ALL'){

		$alert = new NotificationAlert();

		$title = trim($params['title']);
		$_title = strlen($title) > 35 ? substr($title, 0, 35-3).'...' : $title;
		$alert->setTitle($_title);

		$text = trim(strip_tags($params['text']));
		$_text = strlen($text) > 120 ? substr($text, 0, (120-3)).'...' : $text;
		$alert->setText($_text);

		if($params['targetUrl']??false)
			$alert->setTargetUrl($params['targetUrl']);

		if( $params['attachment']??false ){

			$alertIos = new NotificationAlertIos();
			$alertIos->setAttachments([$params['attachment']]);
			$alert->setIos($alertIos);

			$alertAndroid = new NotificationAlertAndroid();
			$alertAndroid->setBigPicture($params['attachment']);
			$alert->setAndroid($alertAndroid);

			$alertWeb = new NotificationAlertWeb();
			$alertWeb->setImage($params['attachment']);
			$alert->setWeb($alertWeb);
		}

		$notification = new Notification();
		$notification->setAlert($alert);

		$params = new DeliveriesCreateParams();

		if( isset($target['segments']) )
			$params->setTargetSegmentIds($target['segments']);
		elseif( isset($target['tags']) )
			$params->setTargetTags($target['tags']);
		else
			$params->setTargetSegmentIds($target);

		$params->setNotification($notification);

		try {

			$response = $this->wonderpush->deliveries()->create($params);

		} catch (Base $e) {

			throw new Exception($e->getMessage(), $e->getCode());
		}

		return $response;
	}
}