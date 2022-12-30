<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\News;
use App\Entity\Term;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method News|null find($id, $lockMode = null, $lockVersion = null)
 * @method News|null findOneBy(array $criteria, array $orderBy = null)
 * @method News[]    findAll()
 * @method News[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NewsRepository extends ResourceRepository
{
	/**
	 * NewsRepository constructor.
	 * @param ManagerRegistry $registry
	 * @param ParameterBagInterface $parameterBag
	 */
	public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, News::class, $parameterBag);
    }


	/**
	 * @param UserInterface $user
	 * @param $limit
	 * @param $offset
	 * @param array $criteria
	 * @return Paginator
	 */
	public function query(UserInterface $user, $limit=20, $offset=0, $criteria=[]){

		/** @var Company $company */
		$company = $user->getCompany();

		/** @var Contact $contact */
		$contact = $user->getContact();

		if( $user->isLegalRepresentative() && $company ){

			$jobs = $company->getCategories();
		}
		else{

			$jobs = ['Agent co'];
		}

		$functions = $contact ? $contact->getFunctions() : ( $company ? $company->getFunctions() : []);
		$functions[] = '';

		$qb = $this->createQueryBuilder('p');

		$qb->where('p.status = :status')
			->andWhere('p.role in (:roles)')
			->setParameter('roles', $user->getRoles())
			->setParameter('status', 'publish');

		if( !empty($jobs) ){

			$qb->leftJoin('p.terms', 't', Join::WITH, 't.taxonomy = \'job\'')
				->andWhere($qb->expr()->orX(
					$qb->expr()->isNull('t.id'),
					$qb->expr()->in('t.title', $jobs)
				));
		}

		if( $criteria['search']??false ){

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->like('p.title', ':search'),
					$qb->expr()->like('p.description', ':search')
				)
			)->setParameter('search', '%'.$criteria['search'].'%');
		}


		if( isset($criteria['category']) && !$criteria['category']->isEmpty() ){

			$qb->leftJoin('p.terms', 't2', Join::WITH, 't2.taxonomy = \'news_category\'')
				->andWhere('t2.id IN (:category)')
				->setParameter('category', $criteria['category']);
		}

		if( isset($criteria['target']) ){

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->in('p.target', [$criteria['target'],'all','']),
					$qb->expr()->isNull('p.target')
				)
			);
		}

		if( $criteria['createdAt']??false ){

			$qb->andWhere('p.createdAt > :createdAt')
				->setParameter('createdAt', $criteria['createdAt']);
		}

		$qb->andWhere(
			$qb->expr()->orX(
				$qb->expr()->in('p.function', $functions),
				$qb->expr()->isNull('p.function')
			)
		);

		if( $criteria['sort'] == 'popular' ){

			$criteria['sort'] = 'views';

			$oneMonth = new DateTime();
			$oneMonth->modify('- 1 month');

			$qb->andWhere('p.createdAt > :createdAt')
				->setParameter('createdAt', $oneMonth);

			$qb->orderBy('p.'.$criteria['sort'], $criteria['order']);
		}
        elseif( $criteria['sort'] == 'averageRate' ) {
            $qb->orderBy('p.'.$criteria['sort'], $criteria['order']);
        }
		else{

			$qb->orderBy('p.featured DESC, p.'.$criteria['sort'], $criteria['order']);
		}

		return $this->paginate($qb, $limit, $offset);
	}


	/**
	 * @param News $news
	 * @param bool $type
	 * @return array
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function hydrate(News $news, $type=false)
	{
		$data = parent::hydrateResource($news, $type);

		/** @var TermRepository $termRepository */
		$termRepository = $this->getEntityManager()->getRepository(Term::class);
		$categories = $news->getTerms('news_category');

		return array_merge($data,[
			'featured' => $news->getFeatured(),
			'target' => $news->getTarget(),
			'link' => $news->getLink(),
			'linkType' => $news->getLinkType(),
			'thumbnail' => $news->getThumbnail(),
			'categories' => $termRepository->hydrateAll($categories),
			'label' => $news->getLabel(),
            'rating' => [
                'average'=> $news->getAverageRatings(),
                'count'=>count($news->getRatings())
            ]
		]);
	}



	/**
	 * @param $id
	 * @param UserInterface $user
	 * @return News|null
	 */
	public function findOneByUser($id, UserInterface $user){

		return $this->findOneBy(['id'=>$id, 'status'=>'publish', 'role'=>$user->getRoles()]);
	}


	/**
	 * @param $target
	 * @return News[]|null
	 */
	public function findUnotified($target){

		$qbn = $this->createQueryBuilder('n');

		$today = new DateTime();
		$tomorrow = new DateTime();

		$today->setTime(0,0);
		$tomorrow = $tomorrow->setTime(23,59);

		$qbn->where($qbn->expr()->orX(
			$qbn->expr()->isNull('n.notified'),
			$qbn->expr()->eq('n.notified', ':notified')
		))
			->andWhere('n.createdAt > :today')
			->andWhere('n.createdAt < :tomorrow')
			->andWhere('n.status = :status')
			->andWhere($qbn->expr()->orX(
				$qbn->expr()->isNull('n.target'),
				$qbn->expr()->eq('n.target', ':target')
			))
			->setParameter('target', $target)
			->setParameter('today', $today)
			->setParameter('status', 'publish')
			->setParameter('tomorrow', $tomorrow)
			->setParameter('notified', false);

		return $qbn->getQuery()->getResult();
	}
}
