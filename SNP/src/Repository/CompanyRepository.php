<?php

namespace App\Repository;

use App\Entity\Address;
use App\Entity\Company;
use App\Entity\CompanyBusinessCard;
use App\Entity\Contact;
use App\Entity\Mail;
use App\Entity\User;
use App\Service\SnpiConnector;
use App\Service\ValueChecker;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @method Company|null find($id, $lockMode = null, $lockVersion = null)
 * @method Company|null findOneBy(array $criteria, array $orderBy = null)
 * @method Company[]    findAll()
 * @method Company[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CompanyRepository extends AbstractRepository
{
	public static $HYDRATE_ADDRESS=2;
	public static $HYDRATE_USER=3;

	private $requestStack;
	private $translator;

    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag, RequestStack $requestStack, TranslatorInterface $translator)
    {
	    $this->requestStack = $requestStack;
	    $this->translator = $translator;

        parent::__construct($registry, Company::class, $parameterBag);
    }

	/**
	 * @param Company|null $company
	 * @param bool $type
	 * @return array
	 */
	public function hydrate(?Company $company, $type=false)
	{
		if( !$company )
			return null;

		$request = $this->requestStack->getCurrentRequest();
		$logo = $request->getSchemeAndHttpHost().$this->getUrl('logo_directory').'/'.$company->getLogo();

		$data = [
			'id'   => $company->getId(),
			'brand' => $company->getBrand(),
			'name' => $company->getName(),
			'city' => $company->getCity(),
			'street' => $company->getStreet(),
			'zip' => $company->getZip(),
			'logo' => $this->exists('logo_directory', $company->getLogo())?$logo:null
        ];

		if( $type >= self::$HYDRATE_ADDRESS ){

			$data['latLng'] = $company->getLatLng();
		}

		if( $type >= self::$HYDRATE_USER ){

			$data['memberId'] = $company->getMemberId();
			$data['software'] = $company->getSoftware();
			$data['adomosKey'] = $company->getAdomosKey();
            $data['email'] = $company->getEmail();
            $data['turnover'] = (bool)$company->getTurnover();
			$data['sales'] = $company->getSales() == 0 || $company->getSales();

			$fee = $this->getPath('fee_directory').'/'.$company->getId().'.pdf';
			$data['feeUpdatedAt'] = file_exists($fee)?filemtime($fee)*1000:false;
		}

		if( $type >= self::$HYDRATE_FULL ){

			$data['website'] = $company->getWebsite();
			$data['phone'] = $company->getPhone();
			$data['facebook'] = $company->getFacebook();
			$data['twitter'] = $company->getTwitter();
			$data['siren'] = $company->getSiren();
			$data['status'] = $company->getStatus();
			$data['legalForm'] = $company->getLegalForm();
			$data['legalRepresentatives'] = false;
			$data['businessCard'] = false;

			$contactRepository = $this->getEntityManager()->getRepository(Contact::class);

			if( $legalRepresentatives = $company->getLegalRepresentatives() )
				$data['legalRepresentatives'] = $contactRepository->hydrateAll($legalRepresentatives);

			$businessCardRepository = $this->getEntityManager()->getRepository(CompanyBusinessCard::class);

			if( $businessCard = $company->getBusinessCard() )
				$data['businessCard'] = $businessCardRepository->hydrate($businessCard, $businessCardRepository::$HYDRATE_FULL);

			$mailRepository = $this->getEntityManager()->getRepository(Mail::class);

			if( $mails = $company->getMails() )
				$data['mails'] = $mailRepository->hydrateAll($mails, $mailRepository::$HYDRATE_FULL);
		}

		return $data;
	}


    /**
     * @param ?Company $company
     * @param bool $active
     * @param array $criteria
     * @param int $limit
     * @param int $offset
     * @return int[]
     */
	public function getContactsId(?Company $company, $active=true, $criteria=[], $limit=999, $offset=0)
	{
        $contacts = $this->getContacts($company, $active, $criteria, $limit, $offset);
        return $this->hydrateAll($contacts, self::$HYDRATE_IDS);
    }

	/**
	 * @param ?Company $company
	 * @param bool $active
	 * @param array $criteria
	 * @param int $limit
	 * @param int $offset
	 * @param bool $paginator
	 * @return Contact[]|Paginator
	 */
	public function getContacts(?Company $company, $active=true, $criteria=[], $limit=999, $offset=0, $paginator=false)
	{
		if( !$company )
			return [];

        /** @var ContactRepository $contactRepository */
        $contactRepository = $this->getEntityManager()->getRepository(Contact::class);
        $qb = $contactRepository->createQueryBuilder('c');

        $qb->leftJoin(Address::class, 'a', Join::WITH, 'a.contact = c.id')
            ->where('a.company = :company')
            ->distinct()
            ->setParameter('company', $company);

        if( $active ){

            $qb->andWhere('a.isActive = 1')
                ->andWhere('a.isArchived = 0');
        }
        else{

            $qb->andWhere('a.isActive = 0')
                ->andWhere('a.isArchived = 1');
        }

        if( !empty($criteria['search']??'') ){

            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('c.lastname', ':search'),
                $qb->expr()->like('c.firstname', ':search')
            ))
                ->setParameter('search', '%'.$criteria['search'].'%');
        }

        $qb->orderBy('c.lastname');

		if( $paginator ){

            return $this->paginate($qb, $limit, $offset);
        }
		else{

            $qb->setMaxResults($limit)
                ->setFirstResult($offset);

            return $qb->getQuery()->getResult();
        }
	}


    /**
     * @param ?Company $company
     * @param bool $active
     * @param array $criteria
     * @return int
     */
	public function getContactsCount(?Company $company, $active=true, $criteria=[])
	{
        //todo: find a way to use getContacts with paginator

		$items = $this->getContacts($company, $active, $criteria);
        return count($items);
	}


	/**
	 * @param Company $company
	 * @param $contact_id
	 * @param bool $active
	 * @return Contact
	 */
	public function getContact(Company $company, $contact_id, $active=true)
	{
		/** @var AddressRepository $addressRepository */
		$addressRepository = $this->getEntityManager()->getRepository(Address::class);

		$params = ['company' => $company, 'contact'=>$contact_id];

		if( $active )
			$params = array_merge($params, ['isActive'=>1, 'isArchived'=>0]);

		if( $contactAddress = $addressRepository->findOneBy($params) )
			return $contactAddress->getContact();

		return null;
	}

	/**
	 * @param Company|null $company
	 * @return array|int[]
	 * @throws ExceptionInterface
	 */
	public function checkup(?Company $company)
	{
		if( !$company )
			return ['count'=>0];

		$count = 0;
		$data = [];
		$now = new DateTime();

		/** @var CompanyRepository $companyRepository */
		$companyRepository = $this->getEntityManager()->getRepository(Company::class);

		$valueChecker = new ValueChecker($this->translator);

		$valueChecker
			->isValid('email', $company->getEmail(), ['isEmpty'])
			->isValid('phone', $company->getPhone(), ['isValidPhone']);

		$invalidData = $valueChecker->getErrors();

		if($invalidData) {

			$entity = $companyRepository->hydrate($company);
			$entity['type'] = 'checkup';
			$entity['entity'] = 'company';
			$entity['createdAt'] = $now->getTimestamp()*1000;
			$entity['invalid'] = ['data'=>$invalidData, 'count'=>count($invalidData)];

			$data[] = $entity;
			$count ++;
		}

		// Check Collaborators Data

		/** @var ContactRepository $contactRepository */
		$contactRepository = $this->getEntityManager()->getRepository(Contact::class);

		/** @var User[] $collaborators */
		$contacts = $companyRepository->getContacts($company);

		foreach ($contacts as $contact) {

			$checkup = $contactRepository->checkup($contact, $company);

			if( $checkup['count'] ){

				$data[] = $checkup['data'];
				$count ++;
			}
		}

		return [
			'count' => $count,
			'data' => $data
		];
	}
}
