<?php

namespace App\Repository;

use App\Entity\DocumentAsset;
use App\Entity\Role;
use App\Entity\Term;
use App\Entity\Document;
use App\Entity\ContactMetadata;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Document|null find($id, $lockMode = null, $lockVersion = null)
 * @method Document|null findOneBy(array $criteria, array $orderBy = null)
 * @method Document[]    findAll()
 * @method Document[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DocumentRepository extends ResourceRepository
{
    /**
     * DocumentRepository constructor.
     * @param ManagerRegistry $registry
     * @param ParameterBagInterface $parameterBag
     */
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Document::class, $parameterBag);
    }

    /**
     * @param $id
     * @param UserInterface $user
     * @return Document|null
     * @throws NonUniqueResultException
     */
    public function findOneByUserRole($id, UserInterface $user)
    {
        return $this->createQueryBuilder('d')
            ->join('d.terms', 't')
            ->where(
                sprintf(
                    'd.%s = :id',
                    is_numeric($id) ? 'id' : 'slug'
                )
            )
            ->andWhere('t.role IN (:roles)')
            ->setParameters([
                'id' => $id,
                'roles' => $user->getRoles()
            ])
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param UserInterface $user
     * @param $limit
     * @param $offset
     * @param array $criteria
     * @return Document[]|array|Paginator
     */
    public function query(UserInterface $user, $limit=20, $offset=0, $criteria=[]){

        if( !(intval($_ENV['DOCUMENTS_ENABLED']??0)) )
            return [];

        /** @var TermRepository $termRepository */
        $termRepository = $this->getEntityManager()->getRepository(Term::class);

        $includedTerms = $termRepository->getIncluded($user, ['category'], [1,2]);
        $excludedTerms = $termRepository->getExcluded($user, ['category'], [1,2]);

        $qb = $this->createQueryBuilder('d')
            ->distinct(true)
            ->leftJoin("d.terms", 't')
            ->where("d.status = :status")
            ->setParameter('status', 'publish');

        if( $criteria['filter'] == 'favorite' || $criteria['sort'] == 'popular' )
            $qb->innerJoin(ContactMetadata::class, 'um', 'WITH', "d.id = um.entityId");

        if( $criteria['filter'] == 'favorite' && $contact = $user->getContact() ){

            $qb->andWhere("um.state = :filter_state")
                ->andWhere("um.contact = :contact")
                ->andWhere("um.type = :filter_type")
                ->setParameter('contact', $contact)
                ->setParameter('filter_type', 'resource')
                ->setParameter('filter_state', 'favorite');
        }
        elseif( $criteria['filter'] == 'year' && $criteria['year'] ){

            $qb->andWhere("d.createdAt >= :createdAfter")
                ->andWhere("d.createdAt <= :createdBefore")
                ->setParameter('createdAfter', $criteria['year'].'-01-01')
                ->setParameter('createdBefore', $criteria['year'].'-12-31');
        }

        if( $criteria['search']??false ){

            //todo: search in keywords

            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like("d.title", ':search'),
                    $qb->expr()->like("d.description", ':search')
                )
            )->setParameter('search', '%'.$criteria['search'].'%');
        }

        if( $criteria['sort'] == 'popular' ){

            $oneMonth = new DateTime();
            $oneMonth->modify('- 1 month');

            $qb->select("COUNT(um.id) AS HIDDEN popularity, d")
                ->andWhere('d.createdAt > :createdAt')
                ->andWhere("um.state = :sort_state")
                ->andWhere("um.type = :sort_type")
                ->setParameter('createdAt', $oneMonth)
                ->setParameter('sort_state', 'read')
                ->setParameter('sort_type', 'resource')
                ->groupBy("d.id")
                ->orderBy('popularity', $criteria['order']);
        }
        elseif( $criteria['sort'] != 'updatedAt' && $criteria['sort']){

            if( in_array($criteria['sort'], ['category', 'kind', 'section']) ){

                $qb->addOrderBy("t.slug", $criteria['order'])
                    ->addOrderBy("d.createdAt", 'desc');
            }
            else
                $qb->addOrderBy("d.".$criteria['sort'], $criteria['order']);
        }

        if( !empty($criteria['category']) ){

            $qb->leftJoin("d.terms", 't2')
                ->andWhere(
                    $qb->expr()->andX(
                        $qb->expr()->in("t.id", ':category'),
                        $qb->expr()->in("t2.id", ':includedTerms')
                    )
                )->andWhere("t2.id IS NOT NULL")
                ->setParameter('category', $criteria['category']);
        }
        else{

            $qb->andWhere("t.id IN (:includedTerms)")->andWhere("t.id IS NOT NULL");
        }

        if( $criteria['createdAt']??false ){

            $qb->andWhere("d.createdAt > :createdAt")
                ->setParameter('createdAt', $criteria['createdAt']);
        }

        $qb->setParameter('includedTerms', $includedTerms);

        if( count($excludedTerms) ){

            $qb2 = $this->createQueryBuilder('d2')
                ->distinct(true)
                ->leftJoin("d2.terms", 't3')
                ->where('t3.id IN (:excludedTerms)');

            $qb->andWhere($qb->expr()->not($qb->expr()->in('d.id', $qb2->getDQL())))
                ->setParameter('excludedTerms', $excludedTerms);
        }

        return $this->paginate($qb, $limit, $offset);
    }

    /**
     * @param Document|null $document
     * @param bool $type
     * @return array
     * @throws ORMException
     * @throws ExceptionInterface
     */
    public function hydrate(?Document $document, $type=false)
    {
        if( !$document )
            return null;

        $data = parent::hydrateResource($document, $type);

        $data['type'] = 'document';
        $data['thumbnail'] = $document->getThumbnail();

        /* @var $termRepository TermRepository */
        $termRepository = $this->getEntityManager()->getRepository(Term::class);

        /* @var $roleRepository RoleRepository */
        $roleRepository = $this->getEntityManager()->getRepository(Role::class);

        $categories = $document->getTerms('category', $roleRepository->getUserRoles());
        $data['categories'] = $termRepository->hydrateAll($categories);

        /* @var $documentAssetRepository DocumentAssetRepository */
        $documentAssetRepository = $this->getEntityManager()->getRepository(DocumentAsset::class);

        if( $assets = $document->getAssets() )
            $data['assets'] = $documentAssetRepository->hydrateAll($assets);

        return $data;
    }
}
