<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\OrderDetail;
use App\Service\EudonetAction;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method OrderDetail|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderDetail|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrderDetail[]    findAll()
 * @method OrderDetail[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrderDetailRepository extends AbstractRepository
{
	private $eudonetAction;

    public function __construct(EudonetAction $eudonetAction, ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
    	$this->eudonetAction = $eudonetAction;

        parent::__construct($registry, OrderDetail::class, $parameterBag);
    }


	/**
	 * @param OrderDetail $orderDetail
	 * @return array
	 * @throws Exception
	 */
	public function hydrate(OrderDetail $orderDetail){

		/** @var ContactRepository $contactRepository */
		$contactRepository = $this->getEntityManager()->getRepository(Contact::class);

		return [
    		'id'=>$orderDetail->getId(),
    		'title'=>$orderDetail->getTitle(),
    		'description'=>$orderDetail->getDescription(),
    		'price'=>$orderDetail->getPrice(),
    		'taxRate'=>$orderDetail->getTaxRate(),
    		'processed'=>$orderDetail->getProcessed(),
    		'processedStep'=>$orderDetail->getProcessedStep(),
    		'quantity'=>$orderDetail->getQuantity(),
    		'productId'=>$orderDetail->getProductId(),
    		'contacts'=>$contactRepository->hydrateAll($orderDetail->getContacts())
	    ];
	}

	/**
	 * @param Contact[] $contacts
	 * @param $productId
	 * @return int|mixed|string|null
	 */
	public function findByContactsAndProductId($contacts, $productId)
	{
		return (
			$this->createQueryBuilder('od')
				->join('od.contacts', 'c', Join::WITH, 'c IN (:contacts)')
				->where('od.productId = :productId')
				->setParameters([
					'contacts' => $contacts,
					'productId' => $productId
				])
				->getQuery()
				->getResult()
		);
	}
}
