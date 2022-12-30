<?php

namespace App\Repository;

use App\Entity\AbstractEntity;
use App\Entity\Address;
use App\Entity\Signatory;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method Signatory|null find($id, $lockMode = null, $lockVersion = null)
 * @method Signatory|null findOneBy(array $criteria, array $orderBy = null)
 * @method Signatory[]    findAll()
 * @method Signatory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SignatoryRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signatory::class);
    }

    /**
     * @param AbstractEntity $entity
     * @param Address|null $address
     * @return Signatory|null
     */
	public function findOneByEntity(AbstractEntity $entity, ?Address $address){

        $entityClass = ClassUtils::getRealClass(get_class($entity));
        $entityId = $entity->getId();

		if( !$address )
			return null;

		$qb = $this->createQueryBuilder('s');

		$qb->leftJoin('s.signature', 'sg')
			->where('sg.entity = :entity')
			->andWhere('sg.entityId = :entityId')
			->andWhere('s.address = :address')
			->setParameter('address', $address)
			->setParameter('entity', $entityClass)
			->setParameter('entityId', $entityId)
			->orderBy('s.id', 'DESC');

		$query = $qb->getQuery();
		$result = $query->getResult();

		return $result[0]??null;
    }

	/**
	 *  @param $entityClass
	 * @param $entityId
	 * @return Signatory[]|null
	 */
	public function findAllByEntity($entityClass, $entityId){

		$qb = $this->createQueryBuilder('s');

		$qb->leftJoin('s.signature', 'sg')
			->where('sg.entity = :entity')
			->andWhere('sg.entityId = :entityId')
			->setParameter('entity', $entityClass)
			->setParameter('entityId', $entityId);

		$query = $qb->getQuery();

		return $query->getResult();
    }
}
