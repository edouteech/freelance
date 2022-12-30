<?php

namespace App\Repository;

use App\Entity\Address;
use App\Entity\Appendix;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\User;
use App\Entity\FormationParticipant;
use App\Entity\FormationCourse;
use App\Service\EudonetAction;
use App\Service\ValueChecker;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @method Contact|null find($id, $lockMode = null, $lockVersion = null)
 * @method Contact|null findOneBy(array $criteria, array $orderBy = null)
 * @method Contact[]    findAll()
 * @method Contact[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContactRepository extends AbstractRepository
{
    public static $HYDRATE_INSTRUCTOR=2;
    public static $HYDRATE_APPENDIX=101;

	private $translator;
	private $eudonetAction;

    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag, TranslatorInterface $translator, EudonetAction $eudonetAction)
    {
        $this->eudonetAction = $eudonetAction;
        $this->translator = $translator;

        parent::__construct($registry, Contact::class, $parameterBag);
    }

    /**
     * @throws ExceptionInterface
     */
    public function hydrate(?Contact $contact, $type=false, $company=null)
    {
	    if( !$contact )
		    return null;

	    $data = [
		    'id' => $contact->getId(),
		    'createdAt' => $contact->getCreatedAt(),
		    'civility' => $contact->getCivility(),
		    'firstname' => $contact->getFirstname(),
		    'lastname' => $contact->getLastname(),
		    'avatar' => $contact->getAvatar(),
		    'birthday' => $this->formatDate($contact->getBirthday()),
		    'hasDashboard' => $contact->getHasDashboard(),
		    'elearning' => $contact->getELearningV2() ? 2: ($contact->hasElearningAccount() ? 1 : false)
	    ];

	    if( $type == self::$HYDRATE_INSTRUCTOR ){

		    $data['type'] = 'instructor';

		    if( $address = $contact->getAddress() ){

			    $data['phone'] = $address->getPhone();
			    $data['email'] = $address->getEmail();
		    }
	    }

	    if( $type >= self::$HYDRATE_FULL ){

		    $data['memberId'] = $contact->getMemberId();
		    $data['legalForm'] = $contact->getLegalForm();
	    }

	    if( $type >= self::$HYDRATE_FULL ){

		    if( !$homeAddress = $contact->getHomeAddress(false) ){

                $homeAddress = new Address();

                try {
                    $this->eudonetAction->createAddress($homeAddress, $contact);
                } catch (\Throwable $e) {}
            }

		    /* @var $addressRepository AddressRepository */
		    $addressRepository = $this->getEntityManager()->getRepository(Address::class);

		    $data['addresses'] = [
			    'home'=>$addressRepository->hydrate($homeAddress),
			    'work'=>false
		    ];

		    /* @var $userRepository UserRepository */
		    $userRepository = $this->getEntityManager()->getRepository(User::class);
			$user = $userRepository->findOneBy(['contact'=>$contact, 'company'=>$company]);

		    if( $company ){

			    $workAddress = $contact->getWorkAddress($company, false);
			    $data['addresses']['work'] = $addressRepository->hydrate($workAddress);

			    $data['isCommercialAgent'] = $workAddress->isCommercialAgent() && $contact->isMember();
			    $data['positions'] = $workAddress->getPositions(true);
			    $data['isActive'] = $workAddress->isActive();
		    }
			else{

				$data['isActive'] = $homeAddress->isActive();
			}

		    $data['hasCustomRoles'] = $user && $user->hasCustomRoles();
		    $data['isLegalRepresentative'] = $contact->isLegalRepresentative($company);
		    $data['email'] = $contact->getEmail($company);
		    $data['isValid'] = $data['isActive'] && !empty($data['email']);
		    $data['hasAccount'] = (bool)$user;
		    $data['isMember'] = $contact->isMember();
	    }

	    return $data;
    }

    /**
     * @param Contact|null $contact
     * @param Company|null $company
     * @return array
     * @throws ExceptionInterface
     */
	public function checkup(?Contact $contact, ?Company $company=null)
	{
		if( !$contact )
			return ['count'=>0];

		$count = 0;
		$valueChecker = new ValueChecker($this->translator);
		$invalidData = [];
		$data = false;
		$now = new DateTime();

		$valueChecker
			->isValid('firstname', $contact->getFirstname(), ['isEmpty'])
			->isValid('lastname', $contact->getLastname(), ['isEmpty']);

		$invalidData['contact'] = $valueChecker->getErrors();
		$count += count($invalidData['contact']);

		if( $contact->isMember() ){

			/** @var Address $homeAddress */
			if( $homeAddress = $contact->getHomeAddress() ){

                $valueChecker
                    ->isValid('email', $homeAddress->getEmail(), ['isValidEmail'])
                    ->isValid('phone', $homeAddress->getPhone(), ['isValidPhone'])
                    ->isValid('street1', $homeAddress->getStreet1(), ['isEmpty'])
                    ->isValid('zip', $homeAddress->getZip(), ['isEmpty'])
                    ->isValid('city', $homeAddress->getCity(), ['isEmpty']);
            }
			else{

			    $valueChecker->addError('Home address is empty');
            }

            $invalidData['homeAddress'] = $valueChecker->getErrors();
            $count += count($invalidData['homeAddress']);
		}
		else{

			if( $workAddress = $contact->getWorkAddress($company) ){

                $valueChecker->isValid('email', $workAddress->getEmail(), ['isValidEmail']);

                if( $contact->isLegalRepresentative($company) ){

                    $valueChecker->isValid('startedAt', $workAddress->getStartedAt(), ['isValidDate']);
                }
                elseif( !$contact->isStudent() ){

					if( $workAddress->getIssuedAt() )
						$valueChecker->isValid('expireAt', $workAddress->getExpireAt(), ['isValidDate']);

                    $valueChecker->isValid('issuedAt', $workAddress->getIssuedAt(), ['isValidDate']);
                }
            }
            else{

                $valueChecker->addError('Work address is empty');
            }

            $invalidData['workAddress'] = $valueChecker->getErrors();
            $count += count($invalidData['workAddress']);
		}

		if($invalidData) {

			$entity = $this->hydrate($contact);
			$entity['type'] = 'checkup';
			$entity['entity'] = $contact->isLegalRepresentative($company) ? 'legalRepresentative' : 'collaborator';
			$entity['invalid'] = ['data'=>$invalidData, 'count'=>$count];
			$entity['createdAt'] = $now->getTimestamp()*1000;

			$data = $entity;
		}

		return [
			'count' => $count,
			'data' => $data
		];
	}

    /**
     * @param $email
     * @param Contact $excludedContact
     * @return Contact|null
     * @throws NonUniqueResultException
     */
    public function findOneByEmail($email, $excludedContact=false)
	{
	    if( !$email || empty($email) )
	        return null;

        $qb = $this->createQueryBuilder('c');

        $qb->join('c.addresses', 'a')
            ->where('a.emailHash = :emailHash')
            ->andWhere('a.isActive = 1')
            ->andWhere('a.isArchived = 0')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('c.status'),
                $qb->expr()->notIn('c.status', ['refused','removed']))
            )
            ->setParameter('emailHash', sha1($email))
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1);

        if( $excludedContact ){

            $qb->andWhere('c.id != :contactId')
                ->setParameter('contactId', $excludedContact->getId());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }


	public function findOneByEmailFirstNameLastname($limit=20, $offset=0, $criteria=[])
	{

		$qb= $this->getEntityManager()->getRepository(FormationParticipant::class)
				 ->createQueryBuilder('fp');


		if(!empty($criteria['search'])) {
			
			$qb->join('fp.contact', 'c')
					->join('fp.formationCourse', 'fc')
					->join('fc.formation', 'f')
				 	->join('c.addresses', 'ad')
					->where('ad.emailHash = :search')
					->orWhere('c.firstname LIKE :search')
					->orWhere('c.lastname LIKE :search')
					->setParameter('search', '%'.$criteria['search'].'%')
					->addSelect('c.firstname, c.lastname, c.id, c.civility', 'f.title');
				
			return $this->paginate($qb, $limit, $offset);

		}
    }

	public function findInstructorByEmailFirstNameLastname($limit=20, $offset=0, $criteria=[])
	{

		$qb= $this->getEntityManager()->getRepository(FormationCourse::class)
				 ->createQueryBuilder('fc');

		if(!empty($criteria['search'])) {
			
			$qb->join('fc.instructor1', 'c')
				->join('fc.instructor2', 'co')
				->join('fc.formation', 'f')
					->Where('c.firstname LIKE :search')
					->orWhere('c.lastname LIKE :search')
					->Where('co.firstname LIKE :search')
					->orWhere('co.lastname LIKE :search')
					->setParameter('search', '%'.$criteria['search'].'%')
					->addSelect('c.firstname, c.lastname, fc.id, c.civility', 'f.title');
				
			return $this->paginate($qb, $limit, $offset);

			// formationcourse, contact, 

		}
	}


	/**
	 * @return array
	 */
	public function countRegistered($start, $end){

		$qb = $this->createQueryBuilder('c');

		$qb->select('COUNT(c.id) as total')
			->where('c.status = :status')
			->andWhere('c.createdAt <= :end')
			->andWhere('c.createdAt >= :start')
			->setParameter('status', 'member')
			->setParameter('start', $start)
			->setParameter('end', $end);

		return $qb->getQuery()->getScalarResult();
	}

	/**
	 * @param array $membersId
	 * 
	 * @return Contact[]
	 */
	public function findByMembersId(array $membersId = [])
	{
		$this->createQueryBuilder('c')
			->where('c.memberId IN (:membersId)')
			->setParameter('membersId', $membersId)
			->getQuery()
			->getResult();
	}

	public function findByListMembersById($membersId)
	{
		$this->createQueryBuilder('c')
		->where('c.memberId = :membersId')
		->setParameter('membersId', $membersId)
			->getQuery()
			->getResult();
	}
}
