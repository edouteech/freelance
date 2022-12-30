<?php

namespace App\Repository;

use App\Entity\Rating;
use App\Entity\Resource;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Role|null find($id, $lockMode = null, $lockVersion = null)
 * @method Role|null findOneBy(array $criteria, array $orderBy = null)
 * @method Role[]    findAll()
 * @method Role[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RatingRepository extends AbstractRepository
{
	public function __construct(ManagerRegistry $registry)
	{
		parent::__construct($registry, Rating::class);
	}

    /**
     * @param Rating|null $rating
     * @return array|bool
     * @throws ORMException
     * @throws ExceptionInterface
     */
    public function hydrate(?Rating $rating)
    {
        if( !$rating )
            return false;

        /* @var $resourceRepository ResourceRepository */
        $resourceRepository = $this->getEntityManager()->getRepository(Resource::class);

        /* @var $userRepository UserRepository */
        $userRepository = $this->getEntityManager()->getRepository(User::class);

        return [
            'id'=>$rating->getId(),
            'comment'=>$rating->getComment(),
            'rate'=>$rating->getRate(),
            'resource'=>$resourceRepository->hydrateResource($rating->getResource()),
            'user' => $userRepository->hydrate($rating->getUser(), $userRepository::$HYDRATE_SIMPLE)
        ];
    }


    /**
     * @param $limit
     * @param $offset
     * @param array $criteria
     * @return Rating[]|array|Paginator
     */
    public function query($limit=20, $offset=0, $criteria=[]){

        $qb = $this->createQueryBuilder('r');

        if( $criteria['hasComment']??'' )
            $qb->where('r.comment IS NOT NULL');

        $qb->addOrderBy('r.'.$criteria['sort'], $criteria['order']);

        return $this->paginate($qb, $limit, $offset);
    }
}
