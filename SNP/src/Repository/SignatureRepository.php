<?php

namespace App\Repository;

use App\Entity\AbstractEntity;
use App\Entity\Signature;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Util\ClassUtils;

/**
 * @method Signature|null find($id, $lockMode = null, $lockVersion = null)
 * @method Signature|null findOneBy(array $criteria, array $orderBy = null)
 * @method Signature[]    findAll()
 * @method Signature[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SignatureRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signature::class);
    }

    /**
     * @param AbstractEntity $entity
     * @param $account
     * @param $status
     * @return Signature|null
     */
    public function findOneByEntity(AbstractEntity $entity, $account, $status='open'){

        $entityClass = ClassUtils::getRealClass(get_class($entity));

        return $this->findOneBy(['entity'=>$entityClass, 'entityId'=>$entity->getId(), 'status'=>$status, 'account'=>$account]);
    }

	/**
	 * @param $entity
	 * @return Signature[]
	 */
	public function findAllExpired($entity){

    	$now = new DateTime();

	    $qb = $this->createQueryBuilder('s');

	    $qb->where('s.expiredAt < :date')
		    ->andWhere('s.status = :status')
		    ->andWhere('s.entity = :entity')
		    ->andWhere('s.fileUploaded = 1')
		    ->setParameter('entity', $entity)
		    ->setParameter('status', 'open')
		    ->setParameter('date', $now);

	    $query = $qb->getQuery();

	    return $query->getResult();
    }
}
