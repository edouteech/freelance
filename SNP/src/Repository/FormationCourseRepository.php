<?php

namespace App\Repository;

use App\Entity\Appendix;
use App\Entity\Company;
use App\Entity\Contact;
use App\Entity\Formation;
use App\Entity\FormationCourse;
use App\Entity\FormationParticipant;
use App\Entity\Signature;
use Cocur\Slugify\Slugify;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method FormationCourse|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormationCourse|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormationCourse[]    findAll()
 * @method FormationCourse[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationCourseRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, FormationCourse::class, $parameterBag);
    }

    public function isSlotAvailable(FormationCourse $formationCourse, Contact $instructor){

        $qb = $this->createQueryBuilder('p');

        $qb->select('count(p.id)')
            ->where('p.status = :status')
            ->andWhere('p.format = :format')
            ->andWhere('p.id != :id')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->eq('p.instructor2', ':instructor'),
                $qb->expr()->eq('p.instructor3', ":instructor")
            ))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->gte('p.startAt', ':startAt'),
                $qb->expr()->lte('p.endAt', ":endAt")
            ))
            ->setParameter('id', $formationCourse->getId())
            ->setParameter('format', $formationCourse->getFormat())
            ->setParameter('status', 'confirmed')
            ->setParameter('startAt', $formationCourse->getStartAt())
            ->setParameter('endAt', $formationCourse->getEndAt())
            ->setParameter('instructor', $instructor);

        return $qb->getQuery()->getSingleScalarResult()?false:true;
    }


    /**
     * @param FormationParticipant[] $formationParticipants
     * @return Appendix[]
     */
    public function findAppendices($formationParticipants){

        $formationParticipantsId = [];

        foreach ($formationParticipants as $formationParticipant){

            if( $formationParticipant->getPresent() )
                $formationParticipantsId[] = $formationParticipant->getId();
        }

        $appendixRepository = $this->getEntityManager()->getRepository(Appendix::class);

        return $appendixRepository->findBy(['entityType'=>'formation_participant', 'type'=>'attestation', 'entityId'=>$formationParticipantsId]);
    }

    /**
     * @param FormationCourse|null $formationCourse
     * @param bool $type
     * @return array|bool
     */
    public function hydrate(?FormationCourse $formationCourse, $type=false)
    {
        if(!$formationCourse )
            return false;

        $data = [
            'id' => $formationCourse->getId(),
            'type' => 'formation_course',
            'remainingPlaces' => $formationCourse->getFormat() == 'e-learning' ? false : $formationCourse->getRemainingPlaces(),
            'format' => $formationCourse->getFormat(),
            'city' => $formationCourse->getCity(),
            'startAt' => $this->formatDate($formationCourse->getStartAt()),
            'endAt' => $this->formatDate($formationCourse->getEndAt()),
            'registerUntil' => $formationCourse->getEndAt()?$this->formatDate($formationCourse->getEndAt()->modify("-1 day")): false,
            'status' => $formationCourse->getStatus(),
            'location' => false
        ];

        if( $type == self::$HYDRATE_FULL ){

            $data['updatedAt'] = $this->formatDate($formationCourse->getUpdatedAt());
            $data['createdAt'] = $this->formatDate($formationCourse->getCreatedAt());

            $data['taxRate'] = $formationCourse->getTaxRate();
            $data['schedule'] = $formationCourse->getSchedule();
            $data['instructors'] = $formationCourse->getAllInstructors();
            $data['seatingCapacity'] = $formationCourse->getSeatingCapacity();
            $data['condition'] = false;

            if(  $formationCourse->getFormat() == 'e-learning' )
                $data['condition'] = $_ENV['E_LEARNING_CONDITION']??false;

            if(  $formationCourse->getFormat() == 'instructor-led' )
                $data['condition'] = $_ENV['INSTRUCTOR_LED_CONDITION']??false;
        }

        if( !in_array($formationCourse->getFormat(), ['e-learning', 'webinar']) && $formationCourseCompany = $formationCourse->getCompany() ){

            /** @var CompanyRepository $companyRepository */
            $companyRepository = $this->getEntityManager()->getRepository(Company::class);
            $data['location'] = $companyRepository->hydrate($formationCourseCompany, $companyRepository::$HYDRATE_ADDRESS);
        }

        if( $formation = $formationCourse->getFormation() ) {

            /** @var FormationRepository $formationRepository */
            $formationRepository = $this->getEntityManager()->getRepository(Formation::class);

            if( $formation = $formationCourse->getFormation() ){

                if( $data['formation'] = $formationRepository->hydrate($formation, $type) )
                    $data['formation']['duration']['days'] = $formationCourse->getDays();
            }
        }

        return $data;
    }


    /**
     * @param $user
     * @param $criteria
     * @return QueryBuilder|array
     */
    public function getQb(?UserInterface $user, $criteria=[]){

        $qb = $this->createQueryBuilder('p');

        $formatStatus = [
            'in-house' => intval($_ENV['TRAINING_IN_HOUSE_ENABLED']??0),
            'instructor-led' => intval($_ENV['TRAINING_INSTRUCTOR_LED_ENABLED']??0),
            'e-learning' => intval($_ENV['TRAINING_ELEARNING_ENABLED']??0),
            'webinar' => intval($_ENV['TRAINING_WEBINAR_ENABLED']??0)
        ];

        $availableStatus = [];

        foreach ( $formatStatus as $format=>$enabled ) {

            if( $enabled )
                $availableStatus[] = $format;
        }

        $qb->where('p.status = :status')
            ->leftJoin(Formation::class, 'f', 'WITH', 'f.id = p.formation');

        if( $criteria['seat'] ){

            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->gte('p.remainingPlaces', $criteria['seat']),
                $qb->expr()->eq('p.format', "'e-learning'")
            ));
        }

        if( $criteria['theme']??false ){

            $slugify = new Slugify();

            $qb->andWhere('f.theme_slug = :theme')
                ->groupBy('f.id')
                ->setParameter('theme', $slugify->slugify($criteria['theme']));
        }

        if( $criteria['formation']??false ){

            $qb->andWhere('f.id = :formation')
                ->setParameter('formation', $criteria['formation']);
        }

        if( $criteria['duration']??false ){

            $qb->andWhere('f.hours = :duration')
                ->setParameter('duration',  $criteria['duration']);
        }
        else{

            $qb->andWhere('f.hours > 0');
        }

        if( $criteria['sort'] == 'ethics' && !$criteria['ethics'] )
            $criteria['ethics'] = 1;

        if( $criteria['sort'] == 'discrimination' && !$criteria['discrimination'] )
            $criteria['discrimination'] = 1;

        if( $criteria['ethics'] )
            $qb->andWhere('f.hoursEthics >= :ethics')->setParameter('ethics', $criteria['ethics']);

        if( $criteria['discrimination'] )
            $qb->andWhere('f.hoursDiscrimination >= :discrimination')->setParameter('discrimination', $criteria['discrimination']);

        if( $criteria['format'] ){

            $criteria['format'] = array_intersect($criteria['format'], $availableStatus);

            if(empty($criteria['format']))
                return [];
        }

        $qb->andWhere('p.format in (:formats)')
            ->setParameter('formats', $criteria['format'] ? $criteria['format'] : $availableStatus);

        $qb->andWhere($qb->expr()->orX(
            $qb->expr()->gt('p.startAt', ':now'),
            $qb->expr()->eq('p.format', "'e-learning'")
        ));

        $qb->distinct();
        $qb->setParameter('now', new DateTime('now'));

        $qb->setParameter('status', 'confirmed');

        if( !empty($criteria['startAt']) )
            $qb->andWhere('p.startAt >= :startAt')->setParameter('startAt', $criteria['startAt']);

        if( !empty($criteria['endAt']) )
            $qb->andWhere('p.endAt <= :endAt')->setParameter('endAt', $criteria['endAt']);

        if( $criteria['updatedAt']??false ){

            $qb->andWhere('p.updatedAt > :updatedAt')
                ->setParameter('updatedAt', $criteria['updatedAt']);
        }

        if( $criteria['search']??false ){

            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('f.title', ':search'),
                    $qb->expr()->like('f.objective', ':search')
                )
            )->setParameter('search', '%'.$criteria['search'].'%');
        }

        if( $criteria['location'] || $criteria['sort'] == 'distance' ){

            if( !$criteria['location'] ){

                if( $user && $userCompany = $user->getCompany() )
                    $criteria['location'] = $userCompany->getLatLng();

                if( !$criteria['location'] )
                    $criteria['location'] = [0,0];
            }

            $qb->addSelect('GEO(c.lat = :lat, c.lng = :lng) as HIDDEN distance')
                ->join(Company::class, 'c', 'WITH', 'c.id = p.company')
                ->andWhere('c.lat is not null')
                ->andWhere('c.lng is not null');

            $qb->setParameter('lat', $criteria['location'][0])
                ->setParameter('lng', $criteria['location'][1]);
        }

        if( $criteria['location'] && $criteria['distance']){

            $qb->having('distance < :distance')
                ->setParameter('distance', $criteria['distance']);
        }

        if( $criteria['createdAt']??false ){

            $qb->andWhere('p.createdAt > :createdAt')
                ->setParameter('createdAt', $criteria['createdAt']);
        }

        return $qb;
    }

    /**
     * @param UserInterface $user
     * @param int $limit
     * @param int $offset
     * @param array $criteria
     * @return Paginator|array
     */
    public function query(UserInterface $user, $limit=20, $offset=0, $criteria=[]){

        $qb = $this->getQb($user, $criteria);

        if( $criteria['sort'] == 'distance' ){

            $qb->orderBy('distance', $criteria['order']);
        }
        elseif($criteria['sort'] == 'duration'){

            $qb->orderBy('f.hours', $criteria['order']);
        }
        elseif($criteria['sort'] == 'ethics'){

            $qb->orderBy('f.hoursEthics', $criteria['order']);
        }
        elseif($criteria['sort'] == 'discrimination'){

            $qb->orderBy('f.hoursDiscrimination', $criteria['order']);
        }
        elseif($criteria['startAt']??false){

            $qb->andWhere('p.startAt >= :startAt')
                ->setParameter('startAt', $criteria['startAt']);
        }
        else{

            $qb->orderBy('p.'.$criteria['sort'], $criteria['order']);
        }

        if( $criteria['sort'] != 'startAt')
            $qb->addOrderBy('p.startAt', 'ASC');

        return $this->paginate($qb, $limit, $offset);
    }


    /**
     * @param $user
     * @param $criteria
     * @return array
     */
    public function getFilters(?UserInterface $user=null, $criteria=[]){

        $qb = $this->getQb($user, $criteria);

        $qb->select('f.theme, f.theme_slug, f.hours, p.format, MAX(f.hoursEthics) as hoursEthics, MAX(f.hoursDiscrimination) as hoursDiscrimination, MAX(p.startAt) as endAt, MIN(p.startAt) as startAt, MAX(p.remainingPlaces) as maxSeat, MIN(p.remainingPlaces) as minSeat')
            ->groupBy('f.theme, f.hours, p.format');

        if( $criteria['location'] || $criteria['sort'] == 'distance' )
            $qb->addSelect('GEO(c.lat = :lat, c.lng = :lng) as HIDDEN distance');

        $result = $qb->getQuery()->getArrayResult();

        $now = date('Y-m-d');

        $filters = [
            'themes'=>[],
            'hours'=>[],
            'hoursEthics'=>[],
            'hoursDiscrimination'=>[],
            'formats'=>[],
            'startAt'=>date('Y-m-d', strtotime('+10 years')),
            'endAt'=>'',
            'maxSeat'=>0,
            'minSeat'=>9999
        ];

        foreach ($result as $row){

            $filters['themes'][$row['theme_slug']] = $row['theme'];
            $filters['hours'][] = $row['hours'];
            $filters['formats'][] = $row['format'];
            $filters['hoursEthics'][] = intval($row['hoursEthics']);
            $filters['hoursDiscrimination'][] = intval($row['hoursDiscrimination']);
            $filters['maxSeat'] = max($filters['maxSeat'], $row['maxSeat']);
            $filters['startAt'] = $row['startAt'] < $filters['startAt'] ? $row['startAt'] : $filters['startAt'];
            $filters['endAt'] = $filters['endAt'] > $row['endAt'] ? $filters['endAt'] : $row['endAt'];
            $filters['minSeat'] = $row['minSeat'] > 0 ? min($filters['minSeat'], $row['minSeat']) : $filters['minSeat'];
        }

        $filters['maxSeat'] = intval($filters['maxSeat']);
        $filters['minSeat'] = intval(min($filters['minSeat'], $filters['maxSeat']));
        $filters['startAt'] = $filters['startAt'] < $now ? $now : $filters['startAt'];

        foreach (['hours', 'hoursEthics', 'hoursDiscrimination', 'formats'] as $type){

            $filters[$type] = array_filter(array_unique($filters[$type]));
            sort($filters[$type]);
        }

        return $filters;
    }


    /**
     * @param FormationCourse $formationCourse
     * @return bool
     * @throws Exception
     */
    public function isActive(FormationCourse $formationCourse){

        $now = new DateTime();
        return $now >= $formationCourse->getStartAt(true) && (!$formationCourse->getEndAt() || $now <= $formationCourse->getEndAt()->modify('+1 day midnight'));
    }


    /**
     * @param FormationCourse $formationCourse
     * @param Contact[] $contacts
     * @return array
     */
    public function filterParticipants(FormationCourse $formationCourse, $contacts){

        /** @var FormationParticipantRepository $formationParticipantRepository */
        $formationParticipantRepository = $this->getEntityManager()->getRepository(FormationParticipant::class);

        $formationParticipants = $formationParticipantRepository->findAllByContacts($contacts, ['formationCourse'=>$formationCourse, 'registered'=>true]);
	    $formationParticipantContactsId = $formationParticipantRepository->hydrateAll($formationParticipants, $formationParticipantRepository::$HYDRATE_IDS);

	    foreach ($contacts as $index=>$contact){

		    if( in_array($contact->getId(), $formationParticipantContactsId) )
			    unset($contacts[$index]);
	    }

	    return $contacts;
    }


	/**
	 * @param $formats
	 * @return int|mixed|string
	 */
	public function getUnreminded($formats)
    {
        $nextDay = new \DateTime();
        $nextDay->modify('+1 day')->setTime(0, 0);

        $qb = $this->createQueryBuilder('fc');

        $qb->where('fc.format IN (:formats)')
            ->andWhere('fc.status = :status')
            ->andWhere('fc.startAt = :nextDay')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('fc.reminded'),
                $qb->expr()->eq('fc.reminded', 0)
            ))
            ->setParameters([
                'formats' => $formats,
                'status' => 'confirmed',
                'nextDay' => $nextDay
            ]);

        return $qb->getQuery()->getResult();
    }

    public function getResendMail($format)
    {
        return $this->findBy(['resendMail'=>true, 'status'=>'confirmed', 'format'=>$format]);
    }

    public function getConfirmed($criteria=[])
    {
        $qb = $this->createQueryBuilder('fc');

        $today = new DateTime();
        $today->setTime(0,0);

        $qb->where('fc.format = :format')
            ->andWhere('fc.status = :status')
            ->setParameters([
                'format' => $criteria['format'],
                'status' => 'confirmed'
            ]);

        if( $criteria['contact']??false ){

            $qb->join('fc.participants', 'fp')
                ->andWhere('fp.contact = :contact')
                ->setParameter('contact', $criteria['contact']);
        }

        if( $criteria['formation']??false )
            $qb->andWhere('fc.formation = :formation')->setParameter('formation', $criteria['formation']);

        if( $criteria['startAt']??false )
            $qb->andWhere('fc.startAt >= :startAt')->setParameter('startAt', $criteria['startAt']);

        if( $criteria['endAt']??false )
            $qb->andWhere('fc.endAt <= :endAt')->setParameter('endAt', $criteria['endAt']);
        else
            $qb->andWhere('fc.endAt < :today')->setParameter('today', $today);

        $qb->orderBy('fc.startAt');

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Contact[] $contacts
     * @param int $limit
     * @param int $offset
     * @return Paginator|array
     */
    public function findByParticipants(array $contacts, $limit=20, $offset=0){

        $qb = $this->createQueryBuilder('f');

        $qb->leftJoin('f.participants', 'p')
            ->leftJoin('p.address', 'a', Join::WITH, 'a.contact = p.contact')
            ->addSelect("(CASE WHEN f.startAt > CURRENT_TIMESTAMP() THEN 0 ELSE 1 END) AS HIDDEN ORD")
            ->where('p.contact IN (:contacts)')
            ->distinct()
            ->andWhere('p.registered = 1')
            ->andWhere('a.isActive = 1')
            ->setParameter('contacts', $contacts)
            ->orderBy('ORD ASC, f.startAt', 'ASC');

        return $this->paginate($qb, $limit, $offset);
    }


	/**
	 * @return array
	 */
	public function getByMonths($status, $start, $end){

		$qb = $this->createQueryBuilder('fc');

		$qb->select('YEAR(fc.updatedAt) as year')
			->addSelect('MONTH(fc.updatedAt) as month')
			->addSelect('COUNT(fc.id) as total')
			->where('fc.status = :status')
			->andWhere('fc.updatedAt <= :end')
			->andWhere('fc.updatedAt >= :start')
			->groupBy('year, month')
			->orderBy('year, month')
			->setParameter('status', $status)
			->setParameter('start', $start)
			->setParameter('end', $end);

		return $qb->getQuery()->getScalarResult();
	}
}
