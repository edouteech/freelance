<?php

namespace App\Repository;

use App\Entity\Sync;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Sync|null find($id, $lockMode = null, $lockVersion = null)
 * @method Sync|null findOneBy(array $criteria, array $orderBy = null)
 * @method Sync[]    findAll()
 * @method Sync[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SyncRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Sync::class, $parameterBag);
    }

	/**
	 * @param $type
	 * @return int|mixed|string
	 */
	public function clean($type){

    	$qb = $this->createQueryBuilder('s');
    	$now = new DateTime();

    	$qb->delete()
		    ->where('s.startedAt < :date')
		    ->andWhere('s.type = :type')
		    ->setParameter('type', $type)
		    ->setParameter('date', $now->modify('-10 days'));

	    $query = $qb->getQuery();

	    return $query->getResult();
    }

	/**
	 * @param $type
	 * @return Sync
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function start($type)
    {
    	$this->clean($type);

        $sync = new Sync();
        $sync->setStartedAt(new DateTime());
        $sync->setType($type);

        $this->save($sync);

	    return $sync;
    }

	/**
	 * @param Sync $sync
	 * @throws ORMException
	 */
	public function end($sync)
    {
	    $sync->setEndedAt(new DateTime());
        $sync->setDuration( $sync->getEndedAt()->getTimestamp() - $sync->getStartedAt()->getTimestamp() );

	    $this->merge($sync);
    }
}
