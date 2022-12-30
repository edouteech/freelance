<?php

namespace App\Repository;

use App\Entity\Address;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Document;
use App\Entity\EudoEntityMetadata;
use App\Entity\Order;
use App\Entity\Registration;
use App\Entity\ContactMetadata;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\UserAccessLog;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends AbstractRepository
{
	public static $HYDRATE_COMPANY = 1000;

    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, User::class, $parameterBag);
    }

	/**
	 * @param UserInterface $user
	 * @param Contact $contact
	 * @return bool
	 */
	public function hasRights(UserInterface $user, Contact $contact)
	{
		if( $user->isLegalRepresentative() ){

			$company = $user->getCompany();
			$companyRepository = $this->getEntityManager()->getRepository(Company::class);

			if( $companyRepository->getContact($company, $contact->getId()) )
				return true;
		}
		else{

			if( $userContact = $user->getContact() )
				return $contact->getId() == $userContact->getId();
		}

		return false;
	}

    /**
     * @param Contact $contact
     * @param Company|null $company
     * @return bool
     */
	public function hasAccount(Contact $contact, ?Company $company=null)
	{
        return (bool)$this->findOneBy(['contact' => $contact, 'company' => $company, 'changePassword' => false]);
	}

    /**
     * @param Contact $contact
     * @param Company|null $company
     * @return User
     */
	public function getAccount(Contact $contact, ?Company $company=null)
	{
        return $this->findOneBy(['contact'=>$contact, 'company'=>$company]);
	}

    /**
     * @param User $user
     * @param bool $inherit
     * @return array
     */
	public function getRoles($user, $inherit=true)
	{
        $roles = $user->getRoles($inherit);

        if( (!$company = $user->getCompany()) || (!$contact = $user->getContact()) )
            return $roles;

        $entityMetadataRepository = $this->getEntityManager()->getRepository(EudoEntityMetadata::class);

        if( !$user->hasCustomRoles() && $companyMetadata = $entityMetadataRepository->findByEntity($company) ){

            if( $companyRoles = $companyMetadata->getData('roles') ){

                $address = $contact->getWorkAddress($company);

                $roleRepository = $this->getEntityManager()->getRepository(Role::class);

                if( $address->isRealEstateAgent() && isset($companyRoles['realEstateAgent']) )
                    $roles = array_merge($roles, $roleRepository->findRolesNameById($companyRoles['realEstateAgent']));
                elseif( $address->isCommercialAgent() && isset($companyRoles['commercialAgent']) )
                    $roles = array_merge($roles, $roleRepository->findRolesNameById($companyRoles['commercialAgent']));
                elseif( isset($companyRoles['otherCollaborator']) )
                    $roles = array_merge($roles, $roleRepository->findRolesNameById($companyRoles['otherCollaborator']));
            }
        }

        return array_unique($roles);
	}

	/**
	 * @param UserInterface $user
	 * @param $id
	 * @return Contact|bool
	 */
	public function getContact(UserInterface $user, $id)
	{
		if( $user->isLegalRepresentative() ){

			$company = $user->getCompany();
			$companyRepository = $this->getEntityManager()->getRepository(Company::class);

			$contact = $companyRepository->getContact($company, $id, false);
		}
		else{

			$contact = $user->getContact();
		}

		if( !$contact || $contact->getId() != $id )
			return false;

		return $contact;
	}

    /**
     * @param User|UserInterface $user
     * @param bool $type
     * @return array
     * @throws ExceptionInterface
     */
	public function hydrate(User $user, $type=100)
	{
		/* @var $companyRepository CompanyRepository */
		$companyRepository = $this->getEntityManager()->getRepository(Company::class);

		/* @var $registrationRepository RegistrationRepository */
		$registrationRepository = $this->getEntityManager()->getRepository(Registration::class);

		/* @var $contactRepository ContactRepository */
		$contactRepository = $this->getEntityManager()->getRepository(Contact::class);

        $company = $user->getCompany();

		if( $type == self::$HYDRATE_COMPANY )
			return $companyRepository->hydrate($company);

		$data = [
			'legacy' => $user->isLegacy(),
			'id' => $user->getId(),
			'login' => $user->getLogin(),
			'isNew' => $user->isNew(),
			'type' => $user->getType(),
			'dashboard' => $user->getDashboard(),
			'lastLogin' => $this->formatDate($user->getLastLoginAt()),
			'isLegalRepresentative' => $user->isLegalRepresentative(),
			'isCommercialAgent' => $user->isCommercialAgent(),
			'isCollaborator' => $user->isCollaborator(),
			'isStudent' => $user->isStudent(),
			'isRegistering' => $user->isRegistering(),
			'changePassword' => $user->getChangePassword(),
			'request_logout_at' => $user->getRequestLogoutAt(),

			'registration' => $registrationRepository->hydrate($user->getRegistration())
		];

        if( $company = $user->getCompany() )
            $data['company'] = $companyRepository->hydrate($company, $companyRepository::$HYDRATE_USER);

        if( $contact = $user->getContact() ){

            if( $contact->getHasDashboard() )
                $data['registration'] = $data['isRegistering'] = false;

            $data['contact'] = $contactRepository->hydrate($contact, ContactRepository::$HYDRATE_FULL);
            $data['contact']['email'] = $contact->getEmail($company);
            $data['contact']['phone'] = $contact->getPhone($company);
        }

		if( $type == self::$HYDRATE_SIMPLE )
            return $data;

        $data['roles'] = $this->getRoles($user);
        $data['staff'] = [];

		/* @var $contactRepository ContactRepository */
		$contactRepository = $this->getEntityManager()->getRepository(Contact::class);

		if( $user->isRegistering() )
			$data['email'] = $user->getLogin();

		if( $contact ){

			$addressRepository = $this->getEntityManager()->getRepository(Address::class);

            $address = $contact->getWorkAddress($company, true);

			$data['contact']['positions'] = $address?$address->getPositions(true):[];
			$data['contact']['addresses'] = $addressRepository->hydrateAll($contact->getAddresses(), $addressRepository::$HYDRATE_COMPANY);
		}

		if( $company ){

			$contacts = $companyRepository->getContacts($company);

			/** @var Contact[] $contacts */
			foreach($contacts as $_contact ){

				$address = $_contact->getAddress($company);
				$_data = $contactRepository->hydrate($_contact);

                $_data['isLegalRepresentative'] = $_contact->isLegalRepresentative($company);
                $_data['hasAccount'] = $this->hasAccount($_contact, $company);
				$_data['email'] = $address ? $address->getEmail() : null;
				$_data['isValid'] = $address && $address->getEmail();

				$data['staff'][] = $_data;
			}
		}

        $data['canCreateAccount'] = $user->getType() == User::$legalRepresentative || ($user->getType() == 'company' && $company->getCanCreateAccount() );

            /* @var $orderRepository OrderRepository */
		$orderRepository = $this->getEntityManager()->getRepository(Order::class);

		$startAt = DateTime::createFromFormat('d-m-Y', $_ENV['PENDING_ORDER_DATE']);
		$pendingOrders = $orderRepository->findByPaymentStatus($user, 'captured', $startAt);

		$data['pendingOrders'] = $orderRepository->hydrateAll($pendingOrders, $orderRepository::$HYDRATE_IDS);

		return $data;
	}

    /**
     * @param Contact|null $contact
     * @param Company|null $company
     * @param string|null $type
     * @param null $changePassword
     * @param null $login
     * @param null $password
     * @return User|false
     * @throws ExceptionInterface
     * @throws ORMException
     * @throws \Exception
     */
	public function create(Contact $contact=null, Company $company=null, $type=null, $changePassword=null, $login=null, $password=null, $encoder=null ){

	    if( ($type == User::$commercialAgent || $type == User::$student) && $company )
	        $company = null;

	    if( ($type == User::$legalRepresentative || $type == 'company') && !$company )
	        throw new \Exception('Company is missing');

		if( !$company && !$contact && !$login )
            throw new \Exception('Data is missing');

		if( ($contact || $company) && $user = $this->findOneBy(['contact'=>$contact, 'company'=>$company]) ){

			$save = false;

			if( $user->getLogin() != $login ){

				$save = true;
				$user->setLogin($login);
			}

			if( $user->getType() != $type ){

				$save = true;
				$user->setType($type);
			}

			if( $save )
				$this->save($user);
		}
		else{

			$user = new User();

            $user->setIsNew(true);

			if( $login )
				$user->setLogin($login);

			if( $password && $encoder )
				$user->setPassword($encoder->encodePassword($user, $password));

			$user->setChangePassword($changePassword);
			$user->setHasNotification(true);
			$user->setType($type);

			if( $contact || $company ){

				if( $contact )
					$user->setContact($contact);

				if( $company )
					$user->setCompany($company);

				$user->setHasConfirmed(true);
			}
			else{

				$user->setHasConfirmed(false);
			}

			$this->save($user);
		}

		return $user;
	}


	/**
	 * @param $limit
	 * @param $offset
	 * @param array $criteria
	 * @return Paginator
	 */
	public function query($limit=20, $offset=0, $criteria=[]){

		$qb = $this->createQueryBuilder('u');

		$qb->addSelect('COUNT(ua.id) AS HIDDEN activity')
			->join(UserAccessLog::class, 'ua', 'WITH', 'u.id = ua.user')
			->groupBy('u.id')
			->addOrderBy($criteria['sort'] == 'activity'?'activity':'u.'.$criteria['sort'], $criteria['order']);

		return $this->paginate($qb, $limit, $offset);
	}


	/**
	 * @param $limit
	 * @param $offset
	 * @return Paginator
	 */
	public function findRegistering($limit=20, $offset=0){

		$qb = $this->createQueryBuilder('u');

		$qb->join('u.registration', 'r')
            ->join('u.contact', 'c')
            ->where('r.validCaci IS NULL')
			->addOrderBy('u.id', 'DESC');

		return $this->paginate($qb, $limit, $offset);
	}


	/**
	 * @param $login
	 * @return Company|Contact
	 */
	public function getRepresentative($login){

		if( substr(strtoupper($login),0,2) == 'AC' ){

			/* @var $contactRepository ContactRepository */
			$contactRepository = $this->getEntityManager()->getRepository(Contact::class);

			$representative = $contactRepository->findOneBy(['memberId'=>$login, 'status'=>'member']);
		}
		else{

			/* @var $companyRepository CompanyRepository */
			$companyRepository = $this->getEntityManager()->getRepository(Company::class);

			$representative = $companyRepository->findOneBy(['memberId'=>$login, 'status'=>'member']);
		}

		return $representative;
	}

	/**
	 * @param $user
	 * @throws DBALException
	 */
	public function removeRefreshTokens(UserInterface $user){

		$em = $this->getEntityManager();
		$query = 'DELETE FROM refresh_tokens WHERE username = :username';

		/** @var DriverStatement $statement */
		$statement = $em->getConnection()->prepare($query);
		$statement->bindValue('username', $user->getUsername());

		$statement->execute();
	}

    /**
     * @param UserInterface $user
     * @param $token
     * @throws Exception
     */
	public function removeRefreshToken(UserInterface $user, $token){

        $em = $this->getEntityManager();
        $query = 'DELETE FROM refresh_tokens WHERE username = :username and refresh_token= :token';

        /** @var DriverStatement $statement */
        $statement = $em->getConnection()->prepare($query);
        $statement->bindValue('username', $user->getUsername());
        $statement->bindValue('token', $token);

        $statement->execute();
	}
}
