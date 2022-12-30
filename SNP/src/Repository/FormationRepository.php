<?php

namespace App\Repository;

use App\Entity\Formation;
use App\Entity\FormationFoad;
use App\Entity\FormationCourse;
use App\Entity\FormationParticipant;
use App\Entity\Order;
use App\Entity\Payment;
use App\Entity\OrderDetail;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Formation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Formation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Formation[]    findAll()
 * @method Formation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Formation::class, $parameterBag);
    }

	/**
	 * @param UserInterface $user
	 * @param int $limit
	 * @param int $offset
	 * @param array $criteria
	 * @return Paginator|array
	 */
	public function query(UserInterface $user, $limit=20, $offset=0, $criteria=[]){

		$qb = $this->createQueryBuilder('p');

		if( $criteria['createdAt']??false ){

			$qb->andWhere('p.createdAt > :createdAt')
				->setParameter('createdAt', $criteria['createdAt']);
		}

		if( $criteria['updatedAt']??false ){

			$qb->andWhere('p.updatedAt > :updatedAt')
				->setParameter('updatedAt', $criteria['updatedAt']);
		}

		$qb->orderBy('p.'.$criteria['sort'], $criteria['order']);

		return $this->paginate($qb, $limit, $offset);
	}


	public function sessionsList($limit=20, $offset=0)
	{
		
			$em = $this->getEntityManager();
			
			$query = $em->createQuery("SELECT f.title , fc.startAt  FROM App\Entity\Formation f,App\Entity\FormationCourse fc, 
										App\Entity\FormationParticipant fp, App\Entity\Order o, App\Entity\OrderDetail od, App\Entity\Payment  p
										WHERE f.format='instructor-led'
										AND  fc.formation = f.id 
										AND o.contact =fp.contact 
										AND fp.formationCourse=fc.id 
										AND od.order=o.id 
										AND od.productId = fc.id 
										AND p.id = o.paymentId
										AND  o.type ='formation' "
									);

									//$paymentId N'EST PAS une clé étrangère dans la table order


		$qb = $query->getResult();

		return $qb;
				
					$qb= $this->getEntityManager()->getRepository(FormationParticipant::class)
						->createQueryBuilder('fp');

					$qb->join('fp.contact', 'c')
							->join('fp.formationCourse', 'fc')
							->join('fc.formation', 'f', 'f.id = fc.formation')
							->join(Order::class,'o','o.contact= c.id')
							->join(OrderDetail::class,'od','od.order= o.id')
							->join(Payment::class,'p','p.Id= o.paymentId')
							->where('f.format = :format')
							->andwhere('o.type = :type')
							->andwhere('o.contact = fp.contact')
							->setParameter('format', 'instructor-led')
							->setParameter('type', 'formation')
							->addSelect('f.startAt, f.title', 'o.updatedAt');
					

			return $this->paginate($qb, $limit, $offset);
			//return $qb;
    }


	/**
	 * @param Formation|null $formation
	 * @param bool $type
	 * @return array
	 */
	public function hydrate(?Formation $formation, $type=false)
	{
		if( !$formation )
			return null;

		$data = [
			'id' => $formation->getId(),
			'type' => 'formation',
			'title' => $formation->getTitle(),
			'price' => $formation->getPrice(),
			'theme' => $formation->getTheme(),
			'theme_slug' => $formation->getThemeSlug(),
			'program' => (bool)$formation->getProgram(),
			'objective' => $formation->getObjective()
		];

		$data['duration'] = [
			'hours'=>$formation->getHours(),
			'hoursEthics'=>$formation->getHoursEthics(),
			'hoursDiscrimination'=>$formation->getHoursDiscrimination(),
			'days'=>$formation->getDays()
		];

		if( $type == self::$HYDRATE_FULL ){

			$data['updatedAt'] = $this->formatDate($formation->getUpdatedAt());
			$data['createdAt'] = $this->formatDate($formation->getCreatedAt());
			$data['format'] = $formation->getFormat();
			$data['code'] = $formation->getCode();

            $data['price'] = $formation->getPrice('member');
            $data['publicPrice'] = $formation->getPrice('not_member');

            $formationFoadRepository = $this->getEntityManager()->getRepository(FormationFoad::class);

            if( $formationFoad = $formationFoadRepository->findOneBy(['formation'=>$formation]) )
                $data['foad'] = $formationFoadRepository->hydrate($formationFoad, $formationFoadRepository::$HYDRATE_FULL);
        }

		return $data;
	}
}
