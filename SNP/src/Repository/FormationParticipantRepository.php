<?php

namespace App\Repository;

use App\Entity\Appendix;
use App\Entity\Contact;
use App\Entity\EudoEntityMetadata;
use App\Entity\Formation;
use App\Entity\FormationCourse;
use App\Entity\FormationParticipant;
use App\Entity\FormationParticipantProgress;
use App\Entity\Poll;
use App\Entity\Signatory;
use App\Entity\Survey;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method FormationParticipant|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormationParticipant|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormationParticipant[]    findAll()
 * @method FormationParticipant[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationParticipantRepository extends AbstractRepository
{
	public static $HYDRATE_CONTACTS=2;

    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, FormationParticipant::class, $parameterBag);
    }


	/**
	 * @param $contacts
	 * @param array $criteria
	 * @return FormationParticipant[]
	 */
	public function findAllByContacts($contacts, $criteria=[]){

		if( !is_iterable($contacts) )
			$contacts = [$contacts];

		$qb = $this->createQueryBuilder('fp');

		$qb->where('fp.contact IN (:contacts)')
			->setParameter('contacts', $contacts);

		foreach ($criteria as $key=>$value){

			$qb->andWhere('fp.'.$key.' = :'.$key)
				->setParameter($key, $value);
		}

		if( !isset($criteria['createdAt']) ){

			$date = new DateTime('3 years ago');
			$date->modify('1 month ago');

			$qb->andWhere('fp.createdAt > :date')
				->setParameter('date', $date);
		}

		return $qb->getQuery()->getResult();
	}


	/**
	 * @param FormationCourse $formationCourse
	 * @param $email
	 * @return FormationParticipant|bool
	 */
	public function findOneByEmail(FormationCourse $formationCourse, $email){

		$participants = $formationCourse->getParticipants();

		foreach ( $participants as $participant ){

			if( $participant->getRegistered() && $participant->getAddress() && $participant->getAddress()->getEmail() == $email )
				return $participant;
		}

		return false;
	}

	/**
	 * @param $contact
	 * @param $formationCourse
	 * @return FormationParticipant|null
	 * @throws NonUniqueResultException
	 */
	public function findOneUnexpired($contact, $formationCourse){

		$date = new DateTime('3 years ago');
		$date->modify('1 month ago');

		$qb = $this->createQueryBuilder('f')
			->where('f.createdAt > :date')
			->andWhere('f.formationCourse = :formationCourse')
			->andWhere('f.contact = :contact')
			->andWhere('f.registered = :registered')
			->setParameter('formationCourse', $formationCourse)
			->setParameter('date', $date)
			->setParameter('registered', true)
			->setParameter('contact', $contact);

		$query = $qb->getQuery();

		return $query->getOneOrNullResult();
	}


    /**
     * @param $formats
     * @return int|mixed|string
     */
    public function getResendMail($formats)
    {
        $qb = $this->createQueryBuilder('f')
            ->join('f.formationCourse', 'fc')
            ->where('f.registered = 1')
            ->andWhere('f.resendMail = 1')
            ->andWhere('fc.status = :status')
            ->andWhere('fc.format IN (:formats)')
            ->setParameter('status', 'confirmed')
            ->setParameter('formats', $formats);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param $contacts
     * @param DateTimeInterface|null $date
     * @return FormationParticipant[]
     */
	public function getLastFormations($contacts, ?DateTimeInterface $date){

		if( !$date )
			return [];

		$date = date('Y-m-d', $date->getTimestamp());

		$qb = $this->createQueryBuilder('f')
			->leftJoin(Appendix::class, 'a', 'WITH', 'f.id = a.entityId')
			->where('a.createdAt > :date')
			->andWhere('a.entityType = :entityType')
			->andWhere('a.type = :type')
			->andWhere('f.contact IN (:contacts)')
			->andWhere('f.present = 1')
			->setParameter('type', 'attestation')
			->setParameter('date', $date)
			->setParameter('entityType', 'formation_participant')
			->setParameter('contacts', $contacts);

		$query = $qb->getQuery();

		return $query->getResult();
	}


	/**
	 * @return FormationParticipant[]
	 */
	public function getUnregistered(){

		$now = new DateTime();
		$now->setTime(0,0);

		$qb = $this->createQueryBuilder('p')
			->leftJoin('p.formationCourse', 'f')
			->where('f.format = :format')
			->andWhere('p.registered = :registered')
			->andWhere('f.status = :status')
			->andWhere('p.registrantId IS NULL')
			->andWhere('f.startAt >= :date')
			->setParameter('format', 'webinar')
			->setParameter('registered', true)
			->setParameter('status', 'confirmed')
			->setParameter('date', $now->format('Y-m-d'));

		$query = $qb->getQuery();

		return $query->getResult();
	}


	/**
	 * @param FormationParticipant[] $items
	 * @param int $type
	 * @return array|bool
	 * @throws ExceptionInterface
	 */
	public function hydrateAll($items, $type=0){

		if( !is_iterable($items) )
			return false;

		if( $type == self::$HYDRATE_IDS ){

			$ids = [];

			foreach ($items as $item)
				$ids[] = $item->getContact()->getId();

			return array_filter($ids);
		}
		else{

			/** @var ContactRepository $contactRepository */
			$contactRepository = $this->getEntityManager()->getRepository(Contact::class);

			$contacts = [];

			foreach ($items as $item){

				$address = $item->getAddress();
				$company = $address?$address->getCompany():null;
				$contact = $contactRepository->hydrate($item->getContact(), $type,$company);

				$data = [
					'id' => $item->getId(),
					'inProgress' => !$item->getPresent() && $item->getProgress(),
					'present' => $item->getPresent(),
					'completed' => $item->getPresent(),
					'registered' => $item->getRegistered(),
					'createdAt' => $this->formatDate($item->getCreatedAt()),
					'confirmed' => $item->getConfirmed(),
					'revived' => $item->getRevived(),
					'absent' => $item->getAbsent(),
					'registrantId' => $item->getRegistrantId(),
					'contact' => $contact,
				];

                $contacts[] = $data;
            }

			return $contacts;
		}
	}


    /**
     * @param FormationParticipant|null $formationParticipant
     * @param bool $type
     * @return array|null
     */
	public function hydrate(?FormationParticipant $formationParticipant, $type=false){

		if( !$formationParticipant )
			return null;

		$formationCourse = $formationParticipant->getFormationCourse();

		$contactRepository = $this->getEntityManager()->getRepository(Contact::class);
		$eudoEntityMetadataRepository = $this->getEntityManager()->getRepository(EudoEntityMetadata::class);

		$formationParticipantMetadata = $eudoEntityMetadataRepository->findByEntity($formationParticipant);

		$contact = $formationParticipant->getContact();
		$address = $formationParticipant->getAddress();

		if( $type == self::$HYDRATE_FULL )
            $data = $contactRepository->hydrate($contact);
		else
		    $data = [];

		$data['id'] = $formationParticipant->getId();

		if( $formationCourse->getWebinarId() ){

			$today = new DateTime();
			$today->setTime(0,0);

            $data['progress'] = false;

            if( $today < $formationCourse->getEndAt() ){

				$data['completed'] = $data['poll'] = $data['survey'] = true;
                $data['ended'] = false;
			}
			else{

				$data['completed'] = $formationParticipantMetadata && $formationParticipantMetadata->getData('completed');

				$pollRepository = $this->getEntityManager()->getRepository(Poll::class);
				$data['poll'] = count($pollRepository->findBy(['formationParticipant'=>$formationParticipant]));

				$surveyRepository = $this->getEntityManager()->getRepository(Survey::class);
				$data['survey'] = count($surveyRepository->findBy(['formationParticipant'=>$formationParticipant])) ? true : false;

                $data['ended'] = true;
			}

			/** @var SignatoryRepository $signatoryRepository */
			$signatoryRepository = $this->getEntityManager()->getRepository(Signatory::class);
			$signatory = $signatoryRepository->findOneByEntity($formationCourse, $address);

			$data['present'] = $signatory && $signatory->getStatus() == 'signed' && $formationParticipant->getPresent();
		}
		else{

			$data['poll'] = $formationParticipant->getPoll();
			$data['survey'] = $formationParticipant->getSurvey();
			$data['present'] = $formationParticipant->getPresent();

            $formationParticipantProgressRepository = $this->getEntityManager()->getRepository(FormationParticipantProgress::class);
            $data['progress'] = $formationParticipantProgressRepository->hydrate($formationParticipant->getProgress());
        }

		if( $address && $type == self::$HYDRATE_FULL){

			$data['phone'] = $address->getPhone();
			$data['email'] = $address->getEmail();
		}

		return $data;
	}


	/**
	 * @param FormationParticipant $formationParticipant
	 * @return Appendix|object
	 */
	public function findAppendix(FormationParticipant $formationParticipant){

		$appendixRepository = $this->getEntityManager()->getRepository(Appendix::class);

		return $appendixRepository->findOneBy(['entityType'=>'formation_participant', 'type'=>'attestation', 'entityId'=>$formationParticipant->getId()]);
	}


    /**
     * @param Formation $formation
     * @param Contact $contact
     * @return int|mixed|string|null
     * @throws NonUniqueResultException
     */
    public function findOneByFormation(Formation $formation, Contact $contact){

        $qb = $this->createQueryBuilder('fp');

        $qb->join('fp.formationCourse', 'fc')
            ->join(Formation::class, 'f', 'with', 'f.id = fc.formation')
            ->where('fc.formation = :formation')
            ->andWhere('fp.registered = true')
            ->andWhere('fp.contact = :contact')
            ->setParameter('contact', $contact)
            ->setParameter('formation', $formation);

        return $qb->getQuery()->getOneOrNullResult();
    }


	/**
	 * @param int $limit
	 * @param int $offset
	 * @param array $criteria
	 * @return FormationParticipant[]|null|Paginator
	 */
    public function query($limit=20, $offset=0, $criteria=[]){

        $qb = $this->createQueryBuilder('fp');

				$qb->innerJoin('fp.contact', 'c')
				 	->innerJoin('c.addresses', 'ad')
					 ->innerJoin('ad.contact', 'co')
					->innerJoin('ad.company', 'comp');

		if( $criteria['formationCourse']??false )
			$qb->where('fp.formationCourse = :formationCourse')
			->setParameter('formationCourse', $criteria['formationCourse']);


			if( $criteria['company']??false ){
					$qb->andWhere('comp.name LIKE :company')
					->setParameter('company', '%'.$criteria['company'].'%');
			}

			if( $criteria['member_id']??false ){
				$qb->andWhere('c.memberId LIKE :member_id')
					->setParameter('member_id', '%'.$criteria['member_id'].'%');
			}

			if( $criteria['search']??false ){

				$qb->andWhere(
					$qb->expr()->orX(
						$qb->expr()->like('co.firstname', ':search'),
						$qb->expr()->like('co.lastname', ':search'),
						$qb->expr()->like('co.civility', ':search')
					)
				)->setParameter('search', '%'.$criteria['search'].'%');
			}

        return $this->paginate($qb, $limit, $offset);
    }


	/**
	 * @return array
	 */
	public function countRegistered($start, $end){

		$qb = $this->createQueryBuilder('p');

		$qb->select('COUNT(p.id) as total')
			->where('p.registered = :registered')
			->andWhere('p.createdAt <= :end')
			->andWhere('p.createdAt >= :start')
			->setParameter('registered', true)
			->setParameter('start', $start)
			->setParameter('end', $end);

		return $qb->getQuery()->getScalarResult();
	}


	/**
	 * @return array
	 */
	public function countPresent($start, $end){

		$qb = $this->createQueryBuilder('p');

		$qb->select('COUNT(p.id) as total')
			->where('p.present = :present')
			->andWhere('p.createdAt <= :end')
			->andWhere('p.createdAt >= :start')
			->setParameter('present', true)
			->setParameter('start', $start)
			->setParameter('end', $end);

		return $qb->getQuery()->getScalarResult();
	}


	/**
	 * @return array
	 */
	public function getbyMonths($status, $start, $end){

		$qb = $this->createQueryBuilder('p');

		$qb->select('YEAR(p.updatedAt) as year')
			->addSelect('MONTH(p.updatedAt) as month')
			->addSelect('COUNT(p.id) as total')
			->where('p.updatedAt <= :end')
			->andWhere('p.updatedAt >= :start')
			->andWhere('p.'.$status.' = :'.$status)
			->groupBy('year, month')
			->orderBy('year, month')
			->setParameter($status, true)
			->setParameter('end', $end)
			->setParameter('start', $start);

		return $qb->getQuery()->getScalarResult();
	}
}
