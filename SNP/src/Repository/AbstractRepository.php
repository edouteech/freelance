<?php

namespace App\Repository;

use App\Entity\AbstractEntity;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\FetchMode;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\Mapping\MappingException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Throwable;

abstract class AbstractRepository extends ServiceEntityRepository
{
	public static $HYDRATE_FULL=100;
	public static $HYDRATE_IDS=1;
	public static $HYDRATE_SIMPLE=0;

	private $parameterBag;

	public function __construct(ManagerRegistry $registry, $entityClass, ParameterBagInterface $parameterBag=null)
	{
		$this->parameterBag = $parameterBag;

		parent::__construct($registry, $entityClass);
	}

    public function getHash( $data )
    {
        return substr(sha1($data), 0, 16);
    }

    /**
     * @param AbstractEntity $entity
     * @return AbstractEntity|null
     */
    public function reload( $entity )
    {
        return $this->find($entity->getId());
    }

	/**
	 * @param $sql
	 * @param array $params
	 * @param int $limit
	 * @param int $offset
	 * @return array
	 * @throws DBALException
	 */
	protected function fetchQuery($sql, $params=[], $limit=50, $offset=0){

		foreach ($params as $key=>$value){

			if( is_array($value) ){
				$value =  "'".implode("','", $value)."'";
				$sql = str_replace(':'.$key, $value, $sql);
				unset($params[$key]);
			}
		}

		$sql = str_replace('\\', '\\\\', $sql);

		$em = $this->getEntityManager();

		$stmt = $em->getConnection()->prepare("SELECT COUNT(*) as count FROM (".$sql.") x");
		$stmt->execute($params);

		$objects = [];
		$count = intval($stmt->fetch(FetchMode::COLUMN));

		if( $count > 0 ){

			$stmt = $em->getConnection()->prepare($sql." LIMIT ".$offset.",".$limit);
			$stmt->execute($params);

			$rows = $stmt->fetchAll();

			$entities = [];
			foreach ($rows as $row){

				if( isset($row['entity'], $row['id']))
					$entities[$row['entity']][] = intval($row['id']);
			}

			foreach ($entities as $entity=>$ids){

				$objects = $em->getRepository($entity)->findBy(['id'=>$ids]);

				$entities[$entity] = [];

				foreach ($objects as $object){

					$entities[$entity][$object->getId()] = $object;
				}
			}

			$objects = [];
			foreach ($rows as $row){

				if( isset($row['entity'], $row['id'], $entities[$row['entity']], $entities[$row['entity']][$row['id']]))
					$objects[] = $entities[$row['entity']][$row['id']];
			}
		}

		return [$objects, $count];
	}

	/**
	 * @param $directory_parameter
	 * @return string
	 */
	public function getPath($directory_parameter){

		return $this->parameterBag->get('kernel.project_dir').$this->parameterBag->get($directory_parameter);
	}

	/**
	 * @param $directory_parameter
	 * @param $file
	 * @return string
	 */
	public function exists($directory_parameter, $file){

		if( !$file )
			return false;

		return file_exists($this->getPath($directory_parameter).'/'.$file);
	}

	/**
	 * @param $directory_parameter
	 * @return string
	 */
	public function getUrl($directory_parameter){

		return str_replace('/public', '', $this->parameterBag->get($directory_parameter));
	}

	/**
	 * @param $items
	 * @param int $type
	 * @return array|bool
	 */
	public function hydrateAll($items, $type=0){

		if( !is_iterable($items) )
			return false;

		$data = [];

		if( $type == self::$HYDRATE_IDS ){

			foreach ($items as $item){

				if(  method_exists($item, 'getId') )
					$data[] = $item->getId();
			}
		}
		else{

			foreach ($items as $item)
				$data[] = $this->hydrate($item, $type);
		}

		return array_filter($data);
	}

	public function formatDate(?DateTimeInterface $datetime)
	{
		if( !$datetime )
			return NULL;

		$datetimeFormat = $_ENV['DATETIME_FORMAT']??'d/m/Y H:m';

		if( $datetimeFormat == 'timestamp')
			return $datetime->getTimestamp()*1000;
		else
			return $datetime->format($datetimeFormat);
	}

	/**
	 * @param $entity
	 * @return void
	 * @throws ORMException
	 * @throws OptimisticLockException
	 */
	public function delete($entity)
	{
		$this->getEntityManager()->remove($entity);
		$this->getEntityManager()->flush();
	}

	/**
	 * @param $entities
	 * @return void
	 * @throws ORMException
	 */
	public function deleteAll($entities)
	{
		foreach ($entities as $entity)
			$this->getEntityManager()->remove($entity);

		$this->getEntityManager()->flush();
	}

	/**
	 * @param $entity
	 * @throws ORMException
	 */
	public function merge($entity)
	{
		$em = $this->getEntityManager();

		//todo: replace with abstract entity merge
		$em->merge($entity);
		$em->flush();
	}

	/**
	 * @param $entity
	 * @return void
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function save(&$entity)
	{
		if( is_array($entity) ){

			$criteria = $entity;

			if( $this->findOneBy($criteria) )
				return;

			$serializer = new Serializer([new ObjectNormalizer()]);
			$entity = $serializer->denormalize($criteria, $this->getClassName(), null);
		}

		$this->getEntityManager()->persist($entity);
		$this->getEntityManager()->flush();
	}


	/**
	 * @throws ConnectionException
	 * @throws DBALException
	 */
	public function truncate()
	{
		$em = $this->getEntityManager();
		$classMetaData = $em->getClassMetadata($this->getClassName());
		$connection = $em->getConnection();
		$dbPlatform = $connection->getDatabasePlatform();
		$connection->beginTransaction();

		try {
			$connection->executeQuery('SET FOREIGN_KEY_CHECKS=0');
			$q = $dbPlatform->getTruncateTableSql($classMetaData->getTableName());
			$connection->executeStatement($q);
			$connection->executeQuery('SET FOREIGN_KEY_CHECKS=1');
			$connection->commit();
		}
		catch (Exception $e) {
			$connection->rollback();
			return false;
		}

		return true;
	}

    /**
     * @param $data
     * @param int $batchSize
     * @param bool $clear
     * @param null $logger
     * @throws ExceptionInterface
     * @throws MappingException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function inserts($data, $batchSize=20, $clear=true, $logger=null){

        $em = $this->getEntityManager();
        $serializer = new Serializer([new ObjectNormalizer()]);
        $classname = $this->getClassName();
        $i = 1;

        foreach ($data as $entity) {

            $entity = $serializer->denormalize($entity, $classname, null);

            //todo: replace with abstract entity merge
            $em->merge($entity);

            if ($batchSize == 0 || ($i % $batchSize) === 0) {

                try {

                    $em->flush();

                    if( $clear )
                        $em->clear();

                } catch (Throwable $t) {

                    if( $logger )
                        $logger->error($t->getMessage());
                }
            }

            if (PHP_SAPI === 'cli') {
                $progression = round(($i / count($data)) * 100);
                echo "\033[5D" . str_pad($progression, 3, ' ', STR_PAD_LEFT) . " %";
            }

            $i++;
        }

        if ($batchSize) {

            try {

                $em->flush();

                if( $clear )
                    $em->clear();

            } catch (Throwable $t) {

                if( $logger )
                    $logger->error($t->getMessage());
            }
        }
    }

    /**
     * @param $data
     * @param int $batchSize
     * @param bool $clear
     * @param null $logger
     * @return bool
     * @throws Exception
     */
	public function bulkInserts($data, $batchSize=20, $clear=true, $logger=null)
	{
		if( !is_array($data) )
			return false;

		$em = $this->getEntityManager();
		$em->getConnection()->getConfiguration()->setSQLLogger(null);

		try {

			$this->inserts($data, $batchSize, $clear, $logger);
		}
		catch (Throwable $t ){

			throw new Exception($t->getMessage());
		}

		if( PHP_SAPI === 'cli' )
			echo "\e[5D100 %".PHP_EOL;

		gc_collect_cycles();

		return true;
	}


	/**
	 * @param QueryBuilder $qb DQL Query Object
	 * @param integer $limit
	 * @param int $offset
	 *
	 * @return Paginator
	 */
	public function paginate(QueryBuilder $qb, int $limit = 10, int $offset = 0)
	{
		$paginator = new Paginator($qb->getQuery());

		$paginator->getQuery()
			->setFirstResult($offset)
			->setMaxResults($limit);

		return $paginator;
	}

	/**
	 * @param $fields
	 * @param $createdAt
	 * @return array|false
	 */
	public function findIdsBy($fields, $createdAt=false){

		$qb = $this->createQueryBuilder('a')
			->select('a.id');

		foreach ($fields as $key=>$value)
			$qb->andWhere('a.'.$key.' NOT IN(:'.$key.')')->setParameter($key, $value);

		if( $createdAt )
			$qb->andWhere('a.createdAt < :createdAt')->setParameter('createdAt', $createdAt);

		$result = $qb->getQuery()->getScalarResult();

		return array_values(array_map('intval', array_column($result, 'id')));
	}

	/**
	 * @param $ids
	 * @param $fields
	 * @return array|false
	 */
	public function bulkUpdate($ids, $fields){

		if(empty($ids))
			return false;

		$qb = $this->createQueryBuilder('a')->update();

		foreach ($fields as $key=>$value){

			if( is_array($value) )
				$value = $value[0];

			$qb->set('a.'.$key, ':'.$key)->setParameter($key, $value);
		}

		$qb->where('a.id IN (:ids)')->setParameter('ids', $ids);

		return $qb->getQuery()->execute();
	}

	/**
	 * @param $ids
	 * @return array|false
	 */
	public function bulkDelete($ids)
	{
		if(empty($ids))
			return false;

		$qb = $this->createQueryBuilder('a')->delete();

		$qb->where('a.id IN (:ids)')->setParameter('ids', $ids);

		return $qb->getQuery()->execute();
	}
}
