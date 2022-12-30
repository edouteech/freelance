<?php

namespace App\Repository;

use App\Entity\Appendix;
use App\Entity\Document;
use App\Entity\Term;
use App\Entity\Resource;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\ORMException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class ResourceRepository extends AbstractRepository
{
	/**
	 * ResourceRepository constructor.
	 * @param ManagerRegistry $registry
	 * @param bool $entityClass
	 * @param null $parameterBag
	 */
	public function __construct(ManagerRegistry $registry, $entityClass=false, $parameterBag=null)
	{
		parent::__construct($registry, $entityClass?:Resource::class, $parameterBag);
	}

	public function getYears()
	{
		$qb = $this->createQueryBuilder('r')
			->select('YEAR(r.createdAt)')
			->distinct();

		$result = $qb->getQuery()->getArrayResult();
		$years = [];

		foreach ($result as $year)
			$years[] = intval(end($year));

		rsort($years);

		return array_values($years);
	}

	/**
	 * @param Resource $resource
	 * @param bool $type
	 * @return array
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function hydrateResource(Resource $resource, $type=false)
	{
		/* @var $termRepository TermRepository */
		$termRepository = $this->getEntityManager()->getRepository(Term::class);

		if( $type == self::$HYDRATE_FULL )
			$this->incrementView($resource);

		return [
			'id'           => $resource->getId(),
			'slug'         => $resource->getSlug(),
			'link'        => $resource->getDashboardLink(),
			'title'        => $resource->getTitle(),
			'type'         => $resource->getType(),
			'entity'       => 'resource',
			'description'  => $resource->getDescription(),
			'sticky'       => $resource->getSticky(),
			'createdAt'    => $this->formatDate($resource->getCreatedAt()),
			'modifiedAt'   => $this->formatDate($resource->getUpdatedAt()),
			'keywords'     => $termRepository->hydrateAll($resource->getTerms('post_tag'))
		];
	}

	/**
	 * @param UserInterface $user
	 * @param $limit
	 * @param $offset
	 * @param array $criteria
	 * @return array
	 * @throws DBALException
	 */
	public function query(UserInterface $user, $limit=20, $offset=0, $criteria=[]){

		if( !(intval($_ENV['DOCUMENTS_ENABLED']??0)) )
			return [[],0];

		/** @var TermRepository $termRepository */
		$termRepository = $this->getEntityManager()->getRepository(Term::class);

		$params['includedTerms'] = $termRepository->hydrateAll($termRepository->getIncluded($user, ['category'], [1,2]), $termRepository::$HYDRATE_IDS);
		$params['excludedTerms'] = $termRepository->hydrateAll($termRepository->getExcluded($user, ['category'], [1,2]), $termRepository::$HYDRATE_IDS);

		//todo: try to use https://gist.github.com/adamsafr/38ef86a9c52d7f258a2a7116f115628d
        $company = $user->getCompany();
        $contact = $user->getContact();

		$params['ext'] = 'pdf';

		if( $criteria['filter'] == 'favorite' ){

		    if( !$contact )
                return [[],0];

			$sql = "SELECT a.id, '".Appendix::class."' AS entity, a.created_at as createdAt FROM appendix a LEFT JOIN contact_metadata u ON u.entity_id = a.id WHERE u.contact_id = :contact_id AND a.ext = :ext AND a.public = 1 AND u.state = 'favorite' AND {filter_by_type} {search_appendix}
			        UNION ALL
			        SELECT DISTINCT r.id, '".Document::class."' AS entity, r.created_at as createdAt FROM resource r LEFT JOIN contact_metadata u ON u.entity_id = r.id LEFT JOIN resource_term t ON t.resource_id = r.id WHERE t.term_id IN (:includedTerms) AND u.contact_id = :contact_id AND u.state = 'favorite' AND r.dtype = 'document' AND r.status = 'publish' AND r.id NOT IN ( SELECT DISTINCT r.id FROM resource r JOIN resource_term t ON t.resource_id = r.id WHERE t.term_id IN (:excludedTerms) ) {search_document}
			        ORDER BY ".$criteria['sort']." ".$criteria['order'];

            $params['contact_id'] = $contact->getId();
        }
		else{

			$sql = "SELECT a.id, '".Appendix::class."' AS entity, a.created_at as createdAt FROM appendix a WHERE {filter_by_type} AND a.ext = :ext AND a.public = 1 {search_appendix}
			        UNION ALL
			        SELECT DISTINCT r.id, '".Document::class."' AS entity, r.created_at as createdAt FROM resource r JOIN resource_term t ON t.resource_id = r.id WHERE t.term_id IN (:includedTerms) AND r.dtype = 'document' AND r.status = 'publish' AND r.id NOT IN ( SELECT DISTINCT r.id FROM resource r JOIN resource_term t ON t.resource_id = r.id WHERE t.term_id IN (:excludedTerms) ) {search_document}
			        ORDER BY ".$criteria['sort']." ".$criteria['order'];
		}

		$search_appendix = $search_document = '';

		if( $criteria['search'] ){

			$search_appendix = "AND (a.title LIKE :search OR a.filename LIKE :search)";
			$search_document = "AND (r.title LIKE :search OR r.description LIKE :search)";

			$params['search'] = '%'.$criteria['search'].'%';
		}

		if( $user->isLegalRepresentative() ){

			$sql = str_replace('{filter_by_type}', 'a.company_id = :company_id AND a.filename NOT LIKE :filename', $sql);
			$params['filename'] = 'AC_%';
            $params['company_id'] = $company->getId();
		}
		else{

            $sql = str_replace('{filter_by_type}', 'a.contact_id = :contact_id', $sql);
            $params['contact_id'] = $contact->getId();
        }

		$sql = str_replace('{search_appendix}', $search_appendix, $sql);
		$sql = str_replace('{search_document}', $search_document, $sql);

		return $this->fetchQuery($sql, $params, $limit, $offset);
	}

	/**
	 * @param Resource|null $ressource
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function incrementView(Resource $ressource=null){

		if( $ressource ){

			$ressource->setViews($ressource->getViews()+1);
			$this->save($ressource);
		}
	}
}
