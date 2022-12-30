<?php

namespace App\Service;

use App\Repository\OptionRepository;
use App\Repository\StatisticsRepository;
use Doctrine\ORM\ORMException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

class EudonetConnector extends AbstractService {

	private $client;
	private $parameters;
	private $tables;

	private $token=false;
	private $expiration=false;

	private $cache;
	private $kernel;
	private $logger;
	private $optionRepository;
	private $statisticsRepository;

	public static $reconnecting=false;

	/**
	 * Eudonet constructor.
	 *
	 * @param ParameterBagInterface $params
	 * @param OptionRepository $optionRepository
	 * @param LoggerInterface $logger
	 * @param KernelInterface $kernel
	 * @param StatisticsRepository $statisticsRepository
	 * @throws Exception
	 */
	public function __construct(ParameterBagInterface $params, OptionRepository $optionRepository, LoggerInterface $logger, KernelInterface $kernel, StatisticsRepository $statisticsRepository){

		$env = $_ENV['APP_ENV']??'prod';

		$this->client = new CurlHttpClient(['verify_peer'=>($env!='dev')]);
		$this->logger = $logger;
		$this->kernel = $kernel;

		$this->optionRepository = $optionRepository;
		$this->statisticsRepository = $statisticsRepository;
		$this->parameters = $params->get('eudonet');
		$this->tables = $this->parameters['tables'];

		$this->cache = new FilesystemAdapter('eudonet', 0, $params->get('kernel.cache_dir'));

		if( $token = $optionRepository->get('eudo_token') )
			$this->token = $token;

		$this->connect();
	}

	/**
	 * Get connection token
	 *
	 * @return string|bool
	 */
	public function getToken(){

		return $this->token;
	}

	/**
	 * Get connection token expiration datetime
	 *
	 * @return string|bool
	 */
	public function getTokenExpiration(){

		return $this->expiration;
	}

	/**
	 * Force connection
	 *
	 * @return string|bool
	 * @throws Exception
	 */
	public function reconnect(){

		self::$reconnecting = true;

		return $this->connect(true);
	}

	/**
	 * Get reconnection status
	 *
	 * @return string|bool
	 * @throws Exception
	 */
	public function isReconnecting(){

		return self::$reconnecting;
	}

	/**
	 * Disconnect from Eudonet
	 *
	 * @return bool
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	public function disconnect(){

		if( $this->request('/Authenticate/Disconnect') ) {

			$this->token = false;

			return true;
		}

		return false;
	}


	/**
	 * @param $table
	 * @param $id
	 * @param $field
	 * @return string|bool
	 * @throws Exception
	 */
	public function getValue($table, $id, $field){

		$data = $this->getValues($table, $id, [$field]);
		return $data[$field]??false;
	}


	/**
	 * @param $table
	 * @param $id
	 * @param $fields
	 * @return string|bool
	 * @throws Exception
	 */
	public function getValues($table, $id, $fields){

		$qb = $this->createQueryBuilder();
		$qb->select($fields)->from($table)->on($id);

		return $this->execute($qb);
	}

	/**
	 * Update Table on id
	 *
	 * @param $table
	 * @param $id
	 * @param $values
	 * @return bool
	 * @throws Exception
	 */
	public function update($table, $id, $values){

		$qb = $this->createQueryBuilder();
		$qb->update($table)->setValues($values)->on($id);

		return $this->execute($qb);
	}

	/**
	 * Insert Table
	 *
	 * @param $table
	 * @param $values
	 * @return bool
	 * @throws Exception
	 */
	public function insert($table, $values){

		$qb = $this->createQueryBuilder();
		$qb->insert($table)->setValues($values);

		return $this->execute($qb);
	}

	/**
	 * Select on id
	 *
	 * @param $fields
	 * @param $table
	 * @param $id
	 * @return bool
	 * @throws Exception
	 */
	public function select($fields, $table, $id){

		$qb = $this->createQueryBuilder();
		$qb->select($fields)->from($table)->on($id);

		return $this->execute($qb);
	}

	/**
	 * Delete by id
	 *
	 * @param $table
	 * @param $id
	 * @return bool
	 * @throws Exception
	 */
	public function delete($table, $id){

		$qb = $this->createQueryBuilder();
		$qb->delete()->from($table)->on($id);

        try {

            return $this->execute($qb);

        } catch (Throwable $t) {

            return false;
        }
    }

	/**
	 * @return EudonetQueryBuilder
	 */
	public function createQueryBuilder(){

		return new EudonetQueryBuilder($this->parameters);
	}


	/**
	 * @return EudonetQueryBuilder
	 */
	public function createExpressionBuilder(){

		return new EudonetQueryBuilder($this->parameters, 'exp');
	}


	/**
	 * @param $table_id
	 * @param $file
	 * @return string
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function getUrl($table_id, $file){

		$url = $_ENV['EUDONET_URL'].'/xrm/datas/'.$_ENV['EUDONET_FOLDER'].'/folders/['.$table_id.']/';

		if( $_ENV['EUDONET_STATS_ENABLED']??false )
			$this->statisticsRepository->log('eudonet', 'GET', $url);

		$url .= $file;

		return $url;
	}

	/**
	 * @param int $model
	 * @param int $table_id
	 * @param int $id
	 * @return string
	 * @throws Exception|ExceptionInterface
	 */
	public function generateFile($model, $table_id, $id){

		$url = $_ENV['EUDONET_URL'].'/specif/'.$_ENV['EUDONET_BASENAME'].'/root/'.$_ENV['EUDONET_PRODUCT'].'/am?descIdTable='.$table_id.'&idModele='.$model;

		if( $_ENV['EUDONET_STATS_ENABLED']??false ){

			$this->statisticsRepository->log('eudonet', 'GET', $url);
			$this->statisticsRepository->log('eudonet', 'GET', $url.'&idFiche='.$id);
		}

		$url .= '&idFiche='.$id;

		try {

			$response = $this->client->request('GET', $url);

			if( $response->getContent() == 'null' ){

				$this->logger->error('Eudonet: '.$url.' failed');
				return false;
			}

			$output = $response->toArray();

			if( !isset($output['Success']) || $output['Success'] !== true ){

				$this->logger->error('Eudonet: '.$url.' failed');
				throw new Exception($output['Message']);
			}

			$url = $output['Message'];

			return $url;

		} catch (Throwable $t) {

			throw new Exception('Eudonet: '.$t->getMessage());
		}
	}


	/**
	 * @param int $table_id
	 * @param $entity_id
	 * @param $field_id
	 * @param $filepath
	 * @return string
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	public function uploadImage($table_id, $entity_id, $field_id, $filepath){

		if( !file_exists($filepath) )
			throw new Exception('Eudonet: File does not exists');

		if( filesize($filepath) >= 5*1000000 )
			throw new Exception('Eudonet: File is too big');

		$options = [
			'DescId'=>$field_id,
			'Value'=> base64_encode(file_get_contents($filepath))
		];

		$response = $this->request('/CUD/Image/'.$table_id.'/'.$entity_id.'/'.basename($filepath), $options);

		return $response['WebFullPath']??'';
	}


	/**
	 * @param int $table_id
	 * @param $entity_id
	 * @param $filepath
	 * @param bool $filename
	 * @return string
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	public function uploadFile($table_id, $entity_id, $filepath, $filename=false){

		if( !file_exists($filepath) )
			throw new Exception('Eudonet: File does not exists');

		if( filesize($filepath) >= 5*1000000 )
			throw new Exception('Eudonet: File is too big');

		if( !$filename )
			$filename = basename($filepath);

		$options = [
			'TabId'=>$table_id,
			'FileId'=> $entity_id,
			'FileName'=> $filename,
			'Content'=> base64_encode(file_get_contents($filepath)),
			'isUrl'=> false
		];

		return $this->request('/Annexes/Add', $options);
	}

    /**
     * @param EudonetQueryBuilder $qb
     * @param int $ttl
     * @return array|bool
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     */
	public function execute(EudonetQueryBuilder $qb, $ttl=0){

		if( !$qb )
			return false;

		switch( $qb->getType() ){

			case 'select':
				if( $id = $qb->getId() )
					$response = $this->request('/Search/'.$qb->getTable().'/'.$id, $ttl);
				else
					$response = $this->request('/Search/'.$qb->getTable(), $qb->getEQL(), [], $ttl);
				break;

			case 'insert':
				$response = $this->request('/CUD/'.$qb->getTable(), $qb->getEQL());
				break;

			case 'update':
				$response = $this->request('/CUD/'.$qb->getTable().'/'.$qb->getId(), $qb->getEQL());
				break;

			case 'delete':
				$response = $this->request('/CUD/Delete/'.$qb->getTable().'/'.$qb->getId());
				break;

			default:
				throw new Exception('Eudonet: Undefined query type');
		}

		if( !$response )
			return false;

		switch( $qb->getType() ){

			case 'select':

				$data = $this->bind($response, $qb->getTableName());

				if( $qb->getId() ){

					if( empty($data) || empty($data[0]) )
						return false;

					$data = $data[0];
				}

				return $data;

			case 'insert':
				return $response['FileId']??false;
		}

		return true;
	}


	/**
	 * Get meta infos
	 *
	 * @param int $table_id
	 * @param array $fields
	 * @return bool|array
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	public function getMetaInfos(int $table_id, array $fields){

		$options = [
			'Tables'=>[[
				'DescId'=>$table_id,
				'Fields'=>$fields
			]]
		];

		$response = $this->call('POST', $_ENV['EUDONET_URL'].'/EudoAPI/MetaInfos/', $options);

		if( !$response['ResultInfos']['Success']??false )
			throw new Exception('Eudonet: '.$response['ResultInfos']['ErrorMessage']??'Unknown error');

		if( isset($response['ResultMetaData'], $response['ResultMetaData']['Tables']) )
			return $response['ResultMetaData']['Tables'];

		return false;
	}

	/**
	 * Connect to Eudonet and get token
	 *
	 * @param bool $force
	 * @return array|bool
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	public function connect($force=false){

		if( $this->token && !$force )
			return true;

		$this->token = false;

		$response = $this->request('/Authenticate/Token', [
			'SubscriberLogin' => $_ENV['EUDONET_SUBSCRIBER_LOGIN'],
			'SubscriberPassword' => $_ENV['EUDONET_SUBSCRIBER_PASSWORD'],
			'BaseName' => $_ENV['EUDONET_BASENAME'],
			'UserLogin' => $_ENV['EUDONET_USER_LOGIN'],
			'UserPassword' => $_ENV['EUDONET_USER_PASSWORD'],
			'UserLang' => 'LANG_'.$_ENV['EUDONET_USER_LANG'],
			'ProductName' => $_ENV['EUDONET_PRODUCT']
		]);

		if( !$response )
			return false;

		$token = $response['Token']??false;
		$expiration = $response['ExpirationDate']??false;

		if( !$token || empty($token) )
			throw new Exception('Eudonet: Invalid token');

		if( !$expiration || empty($expiration) )
			throw new Exception('Eudonet: Invalid token expiration date');

		$this->token = $token;
		$this->expiration = $expiration;

		$this->optionRepository->set('eudo_token', $this->getToken(), $this->getTokenExpiration());

		return true;
	}


	/**
	 * Convert Eudonet search answers to key/value
	 *
	 * @param $rows
	 * @param $table
	 * @return array
	 */
	private function bind($rows, $table){

		$columns = array_flip($this->tables[$table]['columns']);
		$columns_special = [];

		// Clean table to handle pipe
		foreach ($columns as $column_id=>$column) {
			$_column_id = explode('|' , $column_id);
			if( count($_column_id)==2 ){

				if( isset($columns[$_column_id[0]]) )
					$columns[$_column_id[0]] = array_merge((array)$columns[$_column_id[0]], [$column]);
				else
					$columns[$_column_id[0]] = $column;

				unset($columns[$column_id]);
				$columns_special[$column] = $_column_id[1];
			}
		}

		$table_id = $this->tables[$table]['id'];

		$data = [];

		foreach ($rows as $row){

			$item = ['id'=>$row['FileId']];

			foreach ($row['Fields'] as $field){

				$column_id = intval($field['DescId']);

				if( !isset($columns[$column_id]) ){

					if( ($column_id<$table_id || $column_id-$table_id >= 100) && isset($columns[$column_id-1]) )
						$item[$columns[$column_id-1]] = $field['FileId']??NULL;
				}
				else{

					$keys = (array)$columns[$column_id];

					foreach ($keys as $key){

						$column = $columns_special[$key]??false;

						if( $column ){

							$item[$key] = $field[$column]??$field['Value'];
						}
						else{

							//todo: double check if it's the better solution
							$key_last_part = explode('_', $key);
							$key_last_part = end( $key_last_part);

							$item[$key] = $key_last_part == 'id' ? $field['DbValue'] : $field['Value'];
						}

						if( empty($item[$key]) )
							$item[$key] = NULL;
						else
							$item[$key] = trim($item[$key]);
					}
				}
			}

			$data[] = $item;
		}

		return $data;
	}


	/**
	 * Call curl request
	 *
	 * @param $method
	 * @param $url
	 * @param $options
	 * @param bool|int $ttl
	 * @return ResponseInterface|bool
	 * @throws Exception
	 * @throws InvalidArgumentException
	 * @throws ExceptionInterface
	 */
	public function call($method, $url, $options, $ttl=false){

		if( $_ENV['EUDONET_STATS_ENABLED']??false )
			$this->statisticsRepository->log('eudonet', $method, $url);

		$headers = ['Content-Type' => 'application/json'];

		if( $this->token )
			$headers['x-auth'] = $this->token;

		$cache_key = md5($method.':'.$url.'@'.json_encode($options));

		try {

			if( !$ttl ){

				$response = $this->client->request($method, $url, [
					'json' => $options,
					'headers' => $headers
				]);

				if( $url != $_ENV['EUDONET_URL'].'/EudoAPI/Authenticate/Token' ){

					$headers = $response->getHeaders();

					$call_remain_ip = intval($headers['x-call-byip-remain'][0]??9999);
					$call_remain = intval($headers['x-call-remain'][0]??9999);

					$this->optionRepository->set('eudo_call_remain_ip', $call_remain_ip);
					$this->optionRepository->set('eudo_call_remain', $call_remain);

					if( $call_remain_ip < $_ENV['EUDONET_CALL_REMAIN_MIN'] || $call_remain < $_ENV['EUDONET_CALL_REMAIN_MIN'] ){

						$this->statisticsRepository->log('lock', 'GET', '/');
						file_put_contents($this->kernel->getProjectDir().'/.lock', strtotime('now +1 minute'));
					}
				}

				$value = $response->toArray();
			}
			else{

				$value = $this->cache->get($cache_key, function (ItemInterface $item) use($method, $url, $options, $headers, $ttl) {

					$item->expiresAfter($ttl);

					$response = $this->client->request($method, $url, [
						'json' => $options,
						'headers' => $headers
					]);

					$value = $response->toArray();

					if( isset($value['ResultMetaData'], $value['ResultMetaData']['Tables']))
						unset($value['ResultMetaData']['Tables']);

					return $value;
				});
			}

		} catch (Throwable $t) {

			throw new Exception('Eudonet: '.$t->getMessage(), $t->getCode());
		}

		return $value;
	}


	/**
	 * Generate curl request
	 *
	 * @param $path
	 * @param array $params
	 * @param array $data
	 * @param bool $ttl
	 * @return array|bool
	 * @throws ExceptionInterface
	 * @throws InvalidArgumentException
	 */
	public function request($path, $params=[], $data=[], $ttl=false){

		$options = $params;
		if( isset($options['RowsPerPage']) && !$options['RowsPerPage'] )
			$options['RowsPerPage'] = 50;

		if( str_contains($path, 'Delete') )
			$method = 'DELETE';
		else
			$method = empty($params)?'GET':'POST';

		$response = $this->call($method, $_ENV['EUDONET_URL'].'/EudoAPI'.$path, $options, $ttl);

		if( !$response['ResultInfos']['Success']??false ){

			$error_number = $response['ResultInfos']['ErrorNumber']??0;

			if( $error_number >= 100 && $error_number < 200 && !$this->isReconnecting() ){

				$this->reconnect();
				return $this->request($path, $params, $data, $ttl);
			}
			else{

				throw new Exception('Eudonet: '.($response['ResultInfos']['ErrorMessage']??'Unknown error').', path:'.$path.', params:'.json_encode($params), $error_number);
			}
		}
		else{

			if( isset($response['ResultData']['Rows']) ){

				if( PHP_SAPI === 'cli' && empty($data) )
					echo "Requesting " . $response['ResultMetaData']['TotalRows'] . " items\n";

				$data = array_merge($data, $response['ResultData']['Rows']);

				if( isset($response['ResultMetaData']['TotalPages'], $params['NumPage']) && $params['NumPage'] < $response['ResultMetaData']['TotalPages'] ){

					$params['NumPage']++;

					if( PHP_SAPI === 'cli' ){
						$progression = round((($response['ResultMetaData']['RowsByPage']*$response['ResultMetaData']['NumPage'])/$response['ResultMetaData']['TotalRows'])*100);
						echo "\033[5D".str_pad($progression, 3, ' ', STR_PAD_LEFT) . " %";
					}

					return $this->request($path, $params, $data, $ttl);
				}

				if( PHP_SAPI === 'cli' )
					echo "\e[5D".str_pad(100, 3, ' ', STR_PAD_LEFT)." %".PHP_EOL;

				return $data;
			}
			else{

				return $response['ResultData'];
			}
		}
	}

	/**
	 * @param string $descId
	 * @param int $ttl
	 *
	 * @return array|bool
	 */
	public function catalog(string $descId, int $ttl = 0)
	{
		if (! $response = $this->request('/Catalog/'. $descId, $ttl) )
			return false;

		return $response['CatalogValues'];
	}
}
