<?php

namespace App\Repository;

use App\Entity\Agreement;
use App\Entity\Appendix;
use App\Entity\User;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Agreement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Agreement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Agreement[]    findAll()
 * @method Agreement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AgreementRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Agreement::class, $parameterBag);
    }

    public function findByUser(UserInterface $user, $params=[]){

        if( $user->isLegalRepresentative() )
            $params['company'] = $user->getCompany();
        else
            $params['contact'] = $user->getContact();

        return $this->findBy($params);
    }

    public function findAppendices($formationCourse, UserInterface $user, $type){

        $qb = $this->createQueryBuilder('a');

        $qb->join(Appendix::class, 'ax', 'WITH', 'a.id = ax.entityId')
            ->select('ax.id')
            ->where('a.formationCourse = :formationCourse')
            ->andWhere('ax.entityType = :entityType')
            ->andWhere('ax.type = :type')
            ->setParameter('type', $type)
            ->setParameter('entityType', 'agreement')
            ->setParameter('formationCourse', $formationCourse);

        if( $user->isLegalRepresentative() ){

            $qb->andWhere('a.company = :company')
                ->setParameter('company', $user->getCompany() );
        }
        else{

            $qb->andWhere('a.contact = :contact')
                ->setParameter('contact', $user->getContact() );
        }

        $query = $qb->getQuery();

        $results = $query->getArrayResult();

        return array_map(function ($result){ return $result['id']; }, $results);
    }
}
