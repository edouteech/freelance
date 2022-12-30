<?php

namespace App\Repository;

use App\Entity\Appendix;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Term;
use App\Entity\ContactMetadata;
use Cocur\Slugify\Slugify;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Appendix|null find($id, $lockMode = null, $lockVersion = null)
 * @method Appendix|null findOneBy(array $criteria, array $orderBy = null)
 * @method Appendix[]    findAll()
 * @method Appendix[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppendixRepository extends AbstractRepository
{
	public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
	{
		parent::__construct($registry, Appendix::class, $parameterBag);
	}

	/**
	 * @param Appendix|null $appendix
	 * @param bool $type
	 * @return array
	 */
	public function hydrate(?Appendix $appendix, $type=false)
	{
		if( !$appendix )
			return null;

		$contactRepository = $this->getEntityManager()->getRepository(Contact::class);
		$companyRepository = $this->getEntityManager()->getRepository(Company::class);
		$slugify = new Slugify();

		$data = [
			'id'=>$appendix->getId(),
			'slug'=>$appendix->getId().'-'.$slugify->slugify($appendix->getTitle()),
            'title'=>$appendix->getTitle(),
            'link'=>$appendix->getLink(),
            'filename'=>$appendix->getFilename(true),
			'type'=>'appendix',
			'entity'=>'appendix',
			'createdAt'=>$this->formatDate($appendix->getCreatedAt()),
			'assets'=>[
				[
					'title'=>$appendix->getFilename(),
					'type'=>$appendix->getExt(),
					'size'=>$appendix->getSize()
				]
			],
			'categories'=>[
				[
					'title'=> 'Documents administratifs',
					'slug'=> 'documents-administratifs',
					'depth'=> 0
				],
				[
					'title'=> ucfirst(str_replace('-', ' ', $appendix->getType())),
					'slug'=> $appendix->getType(),
					'depth'=> 1
				]
			]
		];

		if( $type == self::$HYDRATE_FULL ){

			$data['contact'] = $contactRepository->hydrate($appendix->getContact());
			$data['company'] = $companyRepository->hydrate($appendix->getCompany());
		}

		return $data;
	}

	/**
	 * @param UserInterface $user
	 * @return mixed
	 */
	public function getTerms(UserInterface $user)
	{
		$qb = $this->createQueryBuilder('a')
			->select('a.type')
			->distinct();

		if( $user->isLegalRepresentative() )
			$qb->where('a.company = :company')->setParameter('company', $user->getCompany());
		else
			$qb->where('a.contact = :contact')->setParameter('contact', $user->getContact());

		$qb->orderBy('a.type', 'asc');

		$query = $qb->getQuery();

		$terms = $query->getResult();

		foreach ($terms as $i=>&$_term){

			$term = new Term();
			$term->setId(999901+$i);
			$term->setTitle(ucfirst(str_replace('-', ' ', $_term['type'])));
			$term->setSlug($_term['type']);
			$term->setTaxonomy('administrative');

			$_term = $term;
		}

		return $terms;
	}

	/**
	 * @param UserInterface $user
	 * @param $id
	 * @return Appendix|bool|null
	 */
	public function findOneByUser(UserInterface $user, $id){

		if( $user->isLegalRepresentative() && $company = $user->getCompany() )
			return $this->findOneBy(['id'=>$id, 'company'=>$company]);
		elseif( $contact = $user->getContact() )
			return $this->findOneBy(['id'=>$id, 'contact'=>$contact]);

		return false;
	}

	/**
	 * @param UserInterface $user
	 * @param $ids
	 * @return Appendix|bool|null
	 */
	public function findByUser(UserInterface $user, $ids)
	{
		$queryBuilder = $this->createQueryBuilder('a');

		if ( $user->isLegalRepresentative() && $company = $user->getCompany() ) {

			return $queryBuilder
				->where('a.id IN (:ids)')
				->andWhere('a.company = :company')
				->setParameters([
					'ids' => $ids,
					'company' => $company
				])
				->getQuery()
				->getResult();
		}
		elseif( $contact = $user->getContact() ){

			return $queryBuilder
				->where('a.id IN (:ids)')
				->andWhere('a.contact = :contact')
				->setParameters([
					'ids' => $ids,
					'contact' => $contact
				])
				->getQuery()
				->getResult();
		}


		return false;
	}


	/**
	 * @param UserInterface $user
	 * @param $limit
	 * @param $offset
	 * @param array $criteria
	 * @return Appendix[]|array|Paginator
	 */
	public function query(UserInterface $user, $limit=20, $offset=0, $criteria=[]){

		if( !(intval($_ENV['DOCUMENTS_ENABLED']??0)) )
			return [];

		$qb = $this->createQueryBuilder('a')
			->where('a.ext = :ext')
			->andWhere('a.public = 1')
			->setParameter('ext', 'pdf');

		if( $user->isLegalRepresentative() )
			$qb->andWhere('a.company = :company')->setParameter('company', $user->getCompany());
		else
			$qb->andWhere('a.contact = :contact')->setParameter('contact', $user->getContact());

		if( $criteria['filter'] == 'favorite' || $criteria['sort'] == 'popular' )
			$qb->innerJoin(ContactMetadata::class, 'cm', 'WITH', 'a.id = cm.entityId');

		if( $criteria['filter'] == 'favorite' && $contact = $user->getContact() ){

			$qb->andWhere('cm.state = :filter_state')
				->andWhere('cm.contact = :contact_meta')
				->andWhere('cm.type = :filter_type')
				->setParameter('contact_meta', $contact)
				->setParameter('filter_type', 'appendix')
				->setParameter('filter_state', 'favorite');
		}
		elseif( $criteria['filter'] == 'year' && $criteria['year'] ){

			$qb->andWhere('a.createdAt >= :createdAfter')
				->andWhere('a.createdAt <= :createdBefore')
				->setParameter('createdAfter', $criteria['year'].'-01-01')
				->setParameter('createdBefore', $criteria['year'].'-12-31');
		}

		if( $criteria['search']??false ){

			//todo: search in keywords

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->like('a.title', ':search'),
					$qb->expr()->like('a.filename', ':search')
				)
			)->setParameter('search', '%'.$criteria['search'].'%');
		}

		if( !empty($criteria['category']) ){

			$criteria['category'] = array_diff($criteria['category'], ['documents-administratifs']);

			if( !empty($criteria['category']) )
				$qb->andWhere('a.type IN (:category)')->setParameter('category', $criteria['category']);
		}

		$criteria['sort'] = $criteria['sort'] == 'category' ? 'type' : $criteria['sort'];

		if( $criteria['sort'] == 'popular' ){

			$qb->select('COUNT(um.id) AS HIDDEN popularity, a')
				->andWhere('um.state = :sort_state')
				->andWhere('um.type = :sort_type')
				->setParameter('sort_state', 'read')
				->setParameter('sort_type', 'appendix')
				->groupBy('a.id')
				->orderBy('popularity', $criteria['order']);
		}
		else{

			$qb->orderBy('a.'.$criteria['sort'], $criteria['order']);
		}

		if( $criteria['createdAt']??false ){

			$qb->andWhere('a.createdAt > :createdAt')
				->setParameter('createdAt', $criteria['createdAt']);
		}

		if( $user->isLegalRepresentative() ){

			$qb->andWhere('a.filename NOT LIKE :filename')
				->setParameter('filename', 'AC_%');
		}

		if( $criteria['sort'] != 'createdAt')
			$qb->addOrderBy('a.createdAt', 'DESC');

		return $this->paginate($qb, $limit, $offset);
	}


	/**
	 * @param UserInterface $user
	 * @return false|int|mixed|string
	 */
	public function fixPublic(UserInterface $user){

		if( $contact = $user->getContact() ){

			$qb = $this->createQueryBuilder('a');

			$qb->update()->set('a.public', 0)
				->where('a.title = :title')->setParameter('title', 'Attestation PJ')
				->andWhere('a.entityType = :entityType')->setParameter('entityType', 'contract')
				->andWhere('a.contact = :contact')->setParameter('contact', $contact)
				->andWhere('a.company IS NOT NULL');

			$qb ->getQuery()->execute();

			$qb = $this->createQueryBuilder('a');

			$qb->update()->set('a.public', 0)
				->where('a.title = :title')->setParameter('title', 'Attestation RCP')
				->andWhere('a.entityType = :entityType')->setParameter('entityType', 'contract')
				->andWhere('a.contact = :contact')->setParameter('contact', $contact)
				->andWhere('a.company IS NULL');


			return $qb ->getQuery()->execute();
		}

		return false;
	}
}
