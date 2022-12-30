<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\User;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Payment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Payment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Payment[]    findAll()
 * @method Payment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaymentRepository extends AbstractRepository
{
	/**
	 * PaymentRepository constructor.
	 * @param ManagerRegistry $registry
	 * @param ParameterBagInterface $parameterBag
	 */
	public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Payment::class, $parameterBag);
    }



	/**
	 * @param Payment $payment
	 * @param bool $type
	 * @return array
	 */
	public function hydrate(Payment $payment, $type=false)
	{
		return [
			'id'=>$payment->getId(),
			'totalAmount'=>$payment->getTotalAmount(),
			'number'=>$payment->getNumber(),
			'clientEmail'=>$payment->getClientEmail(),
			'clientId'=>$payment->getClientId(),
			'street'=>$payment->getStreet(),
			'city'=>$payment->getCity(),
			'zip'=>$payment->getZip(),
			'orderId'=>$payment->getOrder()->getId(),
			'status'=>$payment->getStatus(),
			'tpe'=>$payment->getTpe(),
			'reference'=>$payment->getReference(),
			'entity'=>$payment->getEntity(),
			'updateAt'=>$this->formatDate($payment->getUpdatedAt()),
		];
	}



	/**
	 * @param $limit
	 * @param $offset
	 * @param array $criteria
	 * @return Payment[]|array|Paginator
	 */
	public function query($limit=20, $offset=0, $criteria=[]){

		$qb = $this->createQueryBuilder('p');

        if( $criteria['entity']??'' )
            $qb->where('p.entity = :entity')
                ->setParameter('entity', $criteria['entity']);


		
	     if( $criteria['date']??'' )
		 {
			$qb->where('p.updatedAt <= :date')
			->setParameter('date', $criteria['date']);	
		 }
					

		$qb->addOrderBy('p.'.$criteria['sort'], $criteria['order']);

		return $this->paginate($qb, $limit, $offset);
	}


	/**
	 * @return array
	 */
	public function getSales($start, $end){

		$qb = $this->createQueryBuilder('p');

		$qb->select('SUM(p.totalAmount) as value')
			->addSelect('COUNT(p.id) as total')
			->where('p.status = :status')
			->andWhere('p.updatedAt <= :end')
			->andWhere('p.updatedAt >= :start')
			->setParameter('status', 'captured')
			->setParameter('start', $start)
			->setParameter('end', $end);

		return $qb->getQuery()->getScalarResult();
	}


	/**
	 * @return array
	 */
	public function getSalesbyMonths($start, $end){


		$qb = $this->createQueryBuilder('p');

		$qb->select('YEAR(p.updatedAt) as year')
			->addSelect('MONTH(p.updatedAt) as month')
			->addSelect('SUM(p.totalAmount) as value')
			->addSelect('COUNT(p.id) as total')
			->where('p.status = :status')
			->andWhere('p.updatedAt <= :end')
			->andWhere('p.updatedAt >= :start')
			->groupBy('year, month')
			->orderBy('year, month')
			->setParameter('status', 'captured')
			->setParameter('start', $start)
			->setParameter('end', $end);

		return $qb->getQuery()->getScalarResult();
	}
}
