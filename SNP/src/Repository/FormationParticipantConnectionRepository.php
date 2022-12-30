<?php

namespace App\Repository;

use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use App\Entity\FormationParticipantConnection;

use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method FormationParticipantConnection|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormationParticipantConnection|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormationParticipantConnection[]    findAll()
 * @method FormationParticipantConnection[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationParticipantConnectionRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, FormationParticipantConnection::class, $parameterBag);
    }

	/**
	 * @param $participant
	 * @param $data
	 * @throws ORMException
	 * @throws ExceptionInterface
	 * @throws Exception
	 */
	public function create($participant, $data): void
    {
    	$joinAt = isset($data['join_time']) ? new DateTime($data['join_time']) : null;
    	$leaveAt = isset($data['leave_time']) ? new DateTime($data['leave_time']) : null;
    	$duration = isset($data['duration']) ? intval($data['duration']) : null;

    	if( !$joinAt || !$leaveAt || $this->findOneBy(['formationParticipant'=>$participant, 'joinAt'=>$joinAt, 'leaveAt'=>$leaveAt]) )
    		return;

        $formationParticipantConnection = new FormationParticipantConnection();

	    $formationParticipantConnection
		    ->setFormationParticipant($participant)
		    ->setJoinAt($joinAt)
		    ->setLeaveAt($leaveAt)
		    ->setDuration($duration);

	    $this->save($formationParticipantConnection);
    }
}
