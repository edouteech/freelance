<?php

namespace App\Repository;

use App\Entity\Term;
use App\Entity\User;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Term|null find($id, $lockMode = null, $lockVersion = null)
 * @method Term|null findOneBy(array $criteria, array $orderBy = null)
 * @method Term[]    findAll()
 * @method Term[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TermRepository extends AbstractRepository
{
	public static $HYDRATE_HIERARCHICAL = 101;

    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Term::class, $parameterBag);
    }

	/**
	 * @param $items
	 * @param bool $type
	 * @return array|void
	 */
	public function hydrateAll($items, $type=false)
	{
		$items = parent::hydrateAll($items, $type);

		if( $type >= self::$HYDRATE_HIERARCHICAL ){

			$taxonomies = [];
			$this->sort($items, $taxonomies);
			$items = $taxonomies;
		}

		return $items;
	}

	/**
	 * @param $taxonomies
	 * @param $into
	 * @param int $parentId
	 */
	private function sort(&$taxonomies, &$into, $parentId = 0)
	{
		foreach ($taxonomies as $i => $taxonomy)
		{
			if ($taxonomy['parent'] == $parentId)
			{
				unset($taxonomy['parent']);

				if( $parentId )
					unset($taxonomy['taxonomy']);

				$into[] = $taxonomy;
				unset($taxonomies[$i]);
			}
		}

		foreach ($into as &$topTaxonomy)
		{
			$topTaxonomy['children'] = [];
			$this->sort($taxonomies, $topTaxonomy['children'], $topTaxonomy['id']);

			if( empty($topTaxonomy['children']) )
				unset($topTaxonomy['children']);
		}
	}


	/**
	 * @param Term $term
	 * @param bool $type
	 * @return array
	 */
	public function hydrate(Term $term, $type=false)
	{
		$data = [
			'id' => $term->getId(),
			'slug' => $term->getSlug(),
			'title' => $term->getTitle(),
			'depth' => $term->getDepth(),
			'order' => $term->getOrder()
		];

		if( $type >= self::$HYDRATE_FULL ){

			if( $term->getId() )
				$data['taxonomy'] = $term->getTaxonomy();

			if( $term->getId() )
				$data['parent'] = $term->getParent();
		}

		return $data;
	}

	/**
	 * @param UserInterface $user
	 * @param array $criteria
	 * @return Term[]|array
	 */
	public function query(UserInterface $user, $criteria=[]){

		if( !isset($criteria['roles']) )
			$criteria['roles'] = $user->getRoles();

		if( !isset($criteria['functions']) ){

			$criteria['functions'] = [];

			if( $contact = $user->getContact() )
				$criteria['functions'] = array_merge($criteria['functions'], $contact->getFunctions());

			if( $company = $user->getCompany() )
				$criteria['functions'] = array_merge($criteria['functions'], $company->getFunctions());

			$criteria['functions'] = array_unique($criteria['functions']);
		}

		$qb = $this->createQueryBuilder('d');

		if( ($criteria['exclude']??false) && isset($criteria['roles'], $criteria['functions']) ){

			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->in('d.function', ':functions'),
				$qb->expr()->in('d.role', ':roles')
			))
				->setParameter('functions', $criteria['functions'])
				->setParameter('roles', $criteria['roles']);
		}
		else{

			if( $criteria['roles']??false ){

				$qb->andWhere('d.role in (:roles)')
					->setParameter('roles', $criteria['roles']);
			}

			if( $criteria['functions']??false ){

				$qb->andWhere($qb->expr()->orX(
					$qb->expr()->in('d.function', ':functions'),
					$qb->expr()->isNull('d.function')
				))->setParameter('functions', $criteria['functions']);
			}
			else{

				$qb->andWhere('d.function IS NULL');
			}
		}

		if( $criteria['taxonomies']??false ){

			$qb->andWhere('d.taxonomy in (:taxonomies)')
				->setParameter('taxonomies', $criteria['taxonomies']);
		}

		if( $criteria['depths']??false ){

			$qb->andWhere('d.depth in (:depths)')
				->setParameter('depths', $criteria['depths']);
		}

		if( ($criteria['sort']??false) && ($criteria['order']??false) ){

			$qb->addOrderBy('d.'.$criteria['sort'], $criteria['order']);
		}

		return $qb->getQuery()->getResult();
	}

	/**
	 * @param UserInterface $user
	 * @return Term[]|array
	 */
	public function getIncluded(UserInterface  $user, $taxonomies, $depth){

		return $this->query($user, ['taxonomies'=>$taxonomies, 'depths'=>$depth]);
	}

    /**
     * @param UserInterface $user
     * @param $taxonomies
     * @param $depth
     * @return Term[]|array
     */
	public function getExcluded(UserInterface  $user, $taxonomies, $depth){

		$inaccessibleFunctions = Term::$functions;

		if( $contact = $user->getContact() )
			$inaccessibleFunctions = array_diff($inaccessibleFunctions, $contact->getFunctions());

		if( $company = $user->getCompany() )
			$inaccessibleFunctions = array_diff($inaccessibleFunctions, $company->getFunctions());

		return $this->query($user, ['taxonomies'=>$taxonomies, 'depths'=>$depth, 'roles'=>$user->getInaccessibleRoles(), 'functions'=>$inaccessibleFunctions, 'exclude'=>true]);
	}
}
