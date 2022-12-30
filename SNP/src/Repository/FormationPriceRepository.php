<?php

namespace App\Repository;

use App\Entity\FormationPrice;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method FormationPrice|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormationPrice|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormationPrice[]    findAll()
 * @method FormationPrice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationPriceRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, FormationPrice::class, $parameterBag);
    }
}
