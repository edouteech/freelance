<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Formation;
use App\Entity\Instructor;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * @method Instructor|null find($id, $lockMode = null, $lockVersion = null)
 * @method Instructor|null findOneBy(array $criteria, array $orderBy = null)
 * @method Instructor[]    findAll()
 * @method Instructor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstructorRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Instructor::class);
    }

    /**
     * @param UserInterface $user
     * @param int $limit
     * @param int $offset
     * @param array $criteria
     * @return Paginator|array
     */
    public function query(UserInterface $user, $limit=20, $offset=0, $criteria=[]){

        $qb = $this->createQueryBuilder('i');

        if( $criteria['createdAt']??false ){

            $qb->andWhere('i.createdAt > :createdAt')
                ->setParameter('createdAt', $criteria['createdAt']);
        }

        if( $criteria['updatedAt']??false ){

            $qb->andWhere('i.updatedAt > :updatedAt')
                ->setParameter('updatedAt', $criteria['updatedAt']);
        }

        return $this->paginate($qb, $limit, $offset);
    }

    /**
     * @param Instructor $instructor
     * @param bool $type
     * @return array
     */
    public function hydrate(Instructor $instructor, $type=false){

        $contactRepository = $this->getEntityManager()->getRepository(Contact::class);
        $formationRepository = $this->getEntityManager()->getRepository(Formation::class);

        return [
            'id'=>$instructor->getId(),
            'contact'=>$contactRepository->hydrate($instructor->getContact()),
            'formation'=>$formationRepository->hydrate($instructor->getFormation()),
            'updatedAt'=>$this->formatDate($instructor->getUpdatedAt()),
            'createdAt'=>$this->formatDate($instructor->getCreatedAt())
        ];
    }
}
