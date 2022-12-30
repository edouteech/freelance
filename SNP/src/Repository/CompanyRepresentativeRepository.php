<?php

namespace App\Repository;

use App\Entity\CompanyRepresentative;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method CompanyRepresentative|null find($id, $lockMode = null, $lockVersion = null)
 * @method CompanyRepresentative|null findOneBy(array $criteria, array $orderBy = null)
 * @method CompanyRepresentative[]    findAll()
 * @method CompanyRepresentative[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CompanyRepresentativeRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, CompanyRepresentative::class, $parameterBag);
    }
}
