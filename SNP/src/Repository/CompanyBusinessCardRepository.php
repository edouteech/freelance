<?php

namespace App\Repository;

use App\Entity\CompanyBusinessCard;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method CompanyBusinessCard|null find($id, $lockMode = null, $lockVersion = null)
 * @method CompanyBusinessCard|null findOneBy(array $criteria, array $orderBy = null)
 * @method CompanyBusinessCard[]    findAll()
 * @method CompanyBusinessCard[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CompanyBusinessCardRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, CompanyBusinessCard::class, $parameterBag);
    }


    public function hydrate(?CompanyBusinessCard $businessCard, $type=false)
    {
	    if( !$businessCard )
		    return null;

	    $data = [
		    'id' => $businessCard->getId(),
		    'number' => $businessCard->getNumber(),
		    'issuedAt' => $this->formatDate($businessCard->getIssuedAt()),
		    'expireAt' => $this->formatDate($businessCard->getExpireAt()),
		    'kind' => $businessCard->getKind(),
		    'cci' => $businessCard->getCci()
	    ];

	    if( $type != self::$HYDRATE_FULL ){

	    	$data['active'] = $businessCard->isActive();
	    }

	    return $data;
    }
}
