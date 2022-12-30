<?php

namespace App\Repository;

use App\Entity\Address;
use App\Entity\Company;
use App\Entity\Contact;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method Address|null find($id, $lockMode = null, $lockVersion = null)
 * @method Address|null findOneBy(array $criteria, array $orderBy = null)
 * @method Address[]    findAll()
 * @method Address[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AddressRepository extends AbstractRepository
{
	public static $HYDRATE_COMPANY=10;

    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Address::class, $parameterBag);
    }

    public function findOneByEmail($email, $params=[])
    {
        if( !$email || empty($email) )
            return null;

        if( !$result = $this->findByEmail($email, $params) )
            return null;

        return $result[0]??null;
    }

    public function findByEmail($email, $params=[])
    {
        if( !$email || empty($email) )
            return null;

        $qb = $this->createQueryBuilder('a');

        $qb->join('a.contact', 'c')
            ->where('a.emailHash = :emailHash')
            ->andWhere('a.isActive = 1')
            ->andWhere('a.isArchived = 0');

		if( isset($params['isHome']) )
			$qb->andWhere('a.isHome = :isHome')
				->setParameter('isHome', $params['isHome']);

		if( $params['company']??false )
			$qb->andWhere('a.company = :company')
				->setParameter('company', $params['company']);


		if( isset($params['hasDashboard']) )
			$qb->andWhere('c.hasDashboard = :hasDashboard')
				->setParameter('hasDashboard', $params['hasDashboard']);

		if( isset($params['exclude']) )
			$qb->andWhere('a.id != :exclude')
				->setParameter('exclude', $params['exclude']);

	    $qb->andWhere($qb->expr()->orX(
		    $qb->expr()->isNull('c.status'),
		    $qb->expr()->notIn('c.status', ":statuses")
	    ))
		    ->setParameter('statuses', ['refused','removed'])
		    ->setParameter('emailHash', sha1(strtolower($email)))
            ->groupBy('c.id')
		    ->orderBy('a.contact', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function findAllWithEmail()
    {
        $qb = $this->createQueryBuilder('a');

        $qb->where('a.email IS NOT NULL')
            ->andWhere('a.isArchived = 0');

        return $qb->getQuery()->getResult();
    }

	/**
	 * @param UserInterface $user
	 * @param $limit
	 * @param $offset
	 * @param array $criteria
	 * @return Paginator
	 */
	public function query(UserInterface $user, $limit=20, $offset=0, $criteria=[]){

	    $qb = $this->createQueryBuilder('p')
		    ->where('p.isActive = true')
		    ->andWhere('p.isArchived = false')
		    ->andWhere('p.isExpert = true')
		    ->andWhere('p.endedAt is null')
		    ->innerJoin(Contact::class, 'c', 'WITH', 'p.contact = c.id')
		    ->innerJoin(Company::class, 'cp', 'WITH', 'p.company = cp.id');

		$qb->andWhere('cp.status = :status')->setParameter('status', 'member');

	    if( $criteria['search'] )
	    	$qb->andWhere('c.lastname LIKE :search')->setParameter('search', '%'.$criteria['search'].'%');

	    if( $criteria['location'] ){

		    $qb->addSelect('GEO(p.lat = :lat, p.lng = :lng) as HIDDEN distance')
			    ->setParameter('lat', $criteria['location'][0])
			    ->setParameter('lng', $criteria['location'][1])
			    ->andWhere('p.lat is not null')
			    ->andWhere('p.lng is not null');

		    if( $criteria['distance'] ){

			    $qb->having('distance < :distance')
				    ->setParameter('distance', $criteria['distance']);
		    }

		    $qb->orderBy('distance', $criteria['order']);
	    }
	    else{

	    	if( $criteria['sort'] == 'lastname' )
			    $qb->orderBy('c.'.$criteria['sort'], $criteria['order']);
		    else
			    $qb->orderBy('p.'.$criteria['sort'], $criteria['order']);
	    }

	    $qb->groupBy('c.id');

	    return $this->paginate($qb, $limit, $offset);
    }


    /**
     * @param Contact $contact
     * @param Company|null $company
     * @return int|mixed|string
     */
	public function findAllActive(Contact $contact, ?Company $company){

	    $qb = $this->createQueryBuilder('p')
		    ->innerJoin(Company::class, 'cp', 'WITH', 'p.company = cp.id')
		    ->where('p.contact = :contact')
		    ->andWhere('p.isActive = true')
		    ->andWhere('p.isArchived = false')
		    ->andWhere('p.isHome = false')
		    ->andWhere('cp.status = :status')
		    ->setParameter('status','member')
		    ->setParameter('contact', $contact);

        if( $company ){

            $qb->andWhere('p.company = :company')
                ->setParameter('company', $company);
        }

        $query = $qb->getQuery();

	    return $query->getResult();
	}

	/**
	 * @param Address|null $address
	 * @param bool $type
	 * @return array
	 */
	public function hydrate(?Address $address, $type=false)
	{
		if( !$address )
			return null;

        $addresses = [];

        if( $address->getEmail() )
            $addresses = $this->findByEmail($address->getEmail(), ['hasDashboard'=>true]);

		$data = [
			'id' => $address->getId(),
			'isMain' => $address->isMain(),
			'isActive' => $address->isActive(),
			'isHome' => $address->isHome(),
			'issuedAt' => $this->formatDate($address->getIssuedAt()),
			'email' => $address->getEmail(),
            'emailUnique' => count($addresses)<=1,
			'phone' => $address->getPhone()
		];

		if( $company = $address->getCompany() )
			$data['company'] = $company->getName();

		if( $type == self::$HYDRATE_COMPANY ){

			$companyRepository = $this->getEntityManager()->getRepository(Company::class);

			return array_merge($data, [
				'company' => $companyRepository->hydrate($company)
			]);
		}
		else{
			return array_merge($data, [
				'index' => $address->getIndex(),
				'street' => $address->getStreet(),
				'zip' => $address->getZip(),
				'city' => $address->getCity(),
				'latLng' => $address->getLatLng(),
				'startedAt' => $address->getStartedAt(),
				'endedAt' => $address->getEndedAt(),
				'expireAt' => $address->getExpireAt(),
			]);
		}
	}
}
