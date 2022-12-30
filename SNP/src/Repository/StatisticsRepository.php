<?php

namespace App\Repository;

use App\Entity\Statistics;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Statistics|null find($id, $lockMode = null, $lockVersion = null)
 * @method Statistics|null findOneBy(array $criteria, array $orderBy = null)
 * @method Statistics[]    findAll()
 * @method Statistics[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StatisticsRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Statistics::class);
    }

	/**
	 * @param $category
	 * @param $method
	 * @param $path
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function log($category, $method, $path)
    {
        $log = $this->findOneBy(['path'=>$path, 'method'=>$method, 'category'=>$category]);

        if( !$log ){

	        $log = new Statistics();
	        $log->setPath($path);
	        $log->setCategory($category);
	        $log->setMethod($method);
	        $log->setCount(0);
        }

        $log->setLastCalledAt(new DateTime());
        $log->setCount($log->getCount()+1);

        $this->save($log);
    }
}
