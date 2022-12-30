<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\Page;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Page|null find($id, $lockMode = null, $lockVersion = null)
 * @method Page|null findOneBy(array $criteria, array $orderBy = null)
 * @method Page[]    findAll()
 * @method Page[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageRepository extends ResourceRepository
{
	/**
	 * PageRepository constructor.
	 * @param ManagerRegistry $registry
	 * @param ParameterBagInterface $parameterBag
	 */
	public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Page::class, $parameterBag);
    }


	/**
	 * @param UserInterface $user
	 * @param $limit
	 * @param $offset
	 * @param array $criteria
	 * @return Document[]|array|Paginator
	 */
	public function query(UserInterface $user, $limit=20, $offset=0, $criteria=[]){

		$qb = $this->createQueryBuilder('p')
			->where('p.status = :status')
			->andWhere('p.role in (:roles)')
			->setParameter('roles', $user->getRoles())
			->setParameter('status', 'publish');

		if( $criteria['search']??false ){

			//todo: search in keywords

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->like('p.title', ':search'),
					$qb->expr()->like('p.layout', ':search')
				)
			)->setParameter('search', '%'.$criteria['search'].'%');
		}

		$qb->addOrderBy('p.'.$criteria['sort'], $criteria['order']);

		if( $criteria['createdAt']??false ){

			$qb->andWhere('p.createdAt > :createdAt')
				->setParameter('createdAt', $criteria['createdAt']);
		}

		return $this->paginate($qb, $limit, $offset);
	}

	/**
	 * @param Page $page
	 * @param bool $type
	 * @return array
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function hydrate(Page $page, $type=false)
	{
		$data = parent::hydrateResource($page, $type);

        if( $type == self::$HYDRATE_FULL )
            $data['layout'] = $page->getLayout();

		$data['thumbnail'] = $page->getThumbnail();
        $data['link'] = $page->getSlug();

		return $data;
	}

	/**
	 * @param $id
	 * @param UserInterface $user
	 * @return Page|null
	 */
	public function findOneByUser($id, UserInterface $user){

		return $this->findOneBy(['id'=>$id, 'status'=>'publish', 'role'=>$user->getRoles()]);
	}
}
