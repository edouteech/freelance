<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Formation;
use App\Entity\SurveyComment;
use Doctrine\ORM\ORMException;
use App\Entity\FormationCourse;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method SurveyComment|null find($id, $lockMode = null, $lockVersion = null)
 * @method SurveyComment|null findOneBy(array $criteria, array $orderBy = null)
 * @method SurveyComment[]    findAll()
 * @method SurveyComment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SurveyCommentRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyComment::class);
    }

	/**
	 * @param $comment
	 * @param $formationParticipant
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function create($comment, $formationParticipant){

	    if( !$surveyComment = $this->findOneBy(['formationParticipant'=>$formationParticipant]) ){

            $surveyComment = new SurveyComment();
            $surveyComment->setFormationParticipant($formationParticipant);
        }

		$surveyComment->setValue($comment);

		$this->save($surveyComment);
	}

    /**
     * @param FormationCourse $formationCourse
     * @param Contact|null $contact
     * @return SurveyComment[]|null
     */
	public function findByFormationCourse(FormationCourse $formationCourse, ?Contact $contact=null)
	{
        $qb = $this->createQueryBuilder('sc');

        $qb->join('sc.formationParticipant', 'fp')
            ->join('fp.formationCourse', 'fc', Join::WITH, 'fc = :formationCourse')
            ->setParameter('formationCourse', $formationCourse);

        if( $contact ){

            $qb->where('fp.contact = :contact')
                ->setParameter('contact', $contact);
        }

        return $qb->getQuery()->getResult();
	}

	/**
	 * @param Formation $formation
	 * @return SurveyComment[]|null
	 */
	public function findByFormation(Formation $formation)
	{
		return (
			$this->createQueryBuilder('sc')
				->join('sc.formationParticipant', 'fp')
				->join('fp.formationCourse', 'fc')
				->join('fc.formation', 'f', Join::WITH, 'f = :formation')
				->setParameter('formation', $formation)
				->getQuery()
				->getResult()
		);
	}
}
