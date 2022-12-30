<?php

namespace App\Repository;

use App\Entity\FormationCourse;
use App\Entity\Order;
use App\Entity\OrderDetail;
use App\Entity\Payment;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Order|null find($id, $lockMode = null, $lockVersion = null)
 * @method Order|null findOneBy(array $criteria, array $orderBy = null)
 * @method Order[]    findAll()
 * @method Order[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Order::class, $parameterBag);
    }


	/**
	 * @param FormationCourse $formationCourse
	 * @param UserInterface $user
	 * @return Order[]
	 */
	public function findByFormationCourse(FormationCourse $formationCourse, UserInterface $user){

	    $qb = $this->createQueryBuilder('d')
		    ->join(OrderDetail::class, 'od', 'WITH', 'od.order = d.id')
		    ->where("od.productId = :formationCourseId")
		    ->andWhere("d.contact = :contact")
		    ->andWhere("d.company = :company")
		    ->setParameter('company', $user->getCompany())
		    ->setParameter('contact', $user->getContact())
		    ->setParameter('formationCourseId', $formationCourse->getId())
		    ->setMaxResults( 1 );

	    $query = $qb->getQuery();

	    return $query->getResult();
    }


	/**
	 * @param UserInterface $user
	 * @param $status
	 * @param $startAt
	 * @return Order[]
	 */
	public function findByPaymentStatus(UserInterface $user, $status, $startAt){

	    $qb = $this->createQueryBuilder('o')
		    ->join(Payment::class, 'p', 'WITH', 'p.order = o.id')
		    ->where("o.processed = 0")
		    ->andWhere("p.status = :status")
		    ->andWhere("o.contact = :contact")
		    ->andWhere("o.company = :company")
		    ->andWhere("o.createdAt > :startAt")
		    ->andWhere("o.error IS NULL")
		    ->setParameter('company', $user->getCompany())
		    ->setParameter('contact', $user->getContact())
		    ->setParameter('startAt', $startAt)
		    ->setParameter('status', $status);

	    $query = $qb->getQuery();

	    return $query->getResult();
    }


	/**
	 * @param Order $order
	 * @param bool $type
	 * @return array
	 */
	public function hydrate(Order $order, $type=false)
	{
		/* @var $orderDetailRepository OrderDetailRepository */
		$orderDetailRepository = $this->getEntityManager()->getRepository(OrderDetail::class);

		$payment = $order->getPayment();

		$data = [
			'id'=>$order->getId(),
			'totalAmount'=>$order->getTotalAmount(),
			'totalTax'=>$order->getTotalTax(),
			'type'=>$order->getType(),
			'message'=>$order->getMessage(),
			'processed'=>$order->getProcessed(),
			'payment'=>false
		];

		if( $type  == self::$HYDRATE_FULL )
			$data['details'] = $orderDetailRepository->hydrateAll($order->getDetails());

		if( $payment ){

			$data['payment'] = [
				'status'=>$payment->getStatus(),
				'totalAmount'=>$payment->getTotalAmount(),
				'totalTax'=>$payment->getTotalTax()
			];
		}

		return $data;
	}
}
