<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\SurveyComment;
use Exception;
use App\Entity\Survey;
use App\Entity\Formation;
use App\Entity\SurveyQuestion;
use Doctrine\ORM\ORMException;
use App\Entity\FormationCourse;
use App\Entity\FormationParticipant;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Survey|null find($id, $lockMode = null, $lockVersion = null)
 * @method Survey|null findOneBy(array $criteria, array $orderBy = null)
 * @method Survey[]    findAll()
 * @method Survey[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SurveyRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Survey::class);
    }

    /**
     * @param $answer_id
     * @param $question_id
     * @param FormationParticipant $formationParticipant
     * @throws ExceptionInterface
     * @throws ORMException
     */
	public function create($answer_id, $question_id, FormationParticipant $formationParticipant){

	    $surveyQuestionRepository = $this->getEntityManager()->getRepository(SurveyQuestion::class);

        /** @var SurveyQuestion $surveyQuestion */
        if( !$surveyQuestion = $surveyQuestionRepository->find($question_id) )
	    	throw new Exception("Question is not valid");

	    $answers = $surveyQuestion->getAnswers();
		$surveyAnswer = false;

	    foreach ($answers as $answer){
	    	if( $answer->getId() == $answer_id )
			    $surveyAnswer = $answer;
	    }

	    if( !$surveyAnswer )
		    throw new Exception("Answer is not valid");

	    if( !$survey = $this->findOneBy(['question'=>$surveyQuestion, 'formationParticipant'=>$formationParticipant]) ){

            $survey = new Survey();
            $survey->setQuestion($surveyQuestion);
            $survey->setFormationParticipant($formationParticipant);
        }

        $survey->setAnswer($surveyAnswer);

	    $this->save($survey);
	}

    /**
     * @param FormationCourse $formationCourse
     * @param array $criteria
     * @return array
     */
	public function export(FormationCourse $formationCourse, $criteria=[]){

		if( !$surveys = $this->findByFormationCourse($formationCourse, $criteria['contact']??null) )
			return [];

		$header = [
			'Titre',
			'Date',
			'Participant',
			'Catégorie',
			'Question',
			'Réponse'
		];

		$rows = [];

		array_push( $rows, implode(';', $header) );

		foreach ($surveys as $survey) {

			array_push(
				$rows,
				implode(
					';', [
						(string) $formationCourse->getFormation()->getTitle(),
						(string) $formationCourse->getStartAt()->format('d-m-Y'),
						(string) $survey->getFormationParticipant()->getContact(),
						(string) $survey->getQuestion()->getGroupFields(),
						(string) $survey->getQuestion(),
						(string) $survey->getAnswer()
					]
				)
			);
		}

		$surveyCommentRepository = $this->getEntityManager()->getRepository(SurveyComment::class);

		if( $surveyComments = $surveyCommentRepository->findByFormationCourse($formationCourse, $criteria['contact']??null) ){

			foreach ($surveyComments as $surveyComment) {

				array_push(
					$rows,
					implode(
						';', [
							(string) $formationCourse->getFormation()->getTitle(),
							(string) $formationCourse->getStartAt()->format('d-m-Y'),
							(string) $surveyComment->getFormationParticipant()->getContact(),
							'Commentaire',
							'',
							(string) $surveyComment
						]
					)
				);
			}
		}

		return $rows;
	}

    /**
     * @param FormationCourse $formationCourse
     * @param Contact|null $contact
     * @return Survey[]
     */
    public function findByFormationCourse(FormationCourse $formationCourse, ?Contact $contact=null)
	{
        $qb = $this->createQueryBuilder('s');

        $qb->join('s.formationParticipant', 'fp')
            ->join('fp.formationCourse', 'fc', Join::WITH, 'fc = :formationCourse')
            ->setParameter('formationCourse', $formationCourse);

        if( $contact ){

            $qb->where('fp.contact = :contact')
                ->setParameter('contact', $contact);
        }

        return $qb->getQuery()->getResult();
	}

	private function createStatisticsQueryBuilder()
	{
		return (
			$this->createQueryBuilder('s')
				->select('g.title AS group', 'sq.title AS question', 'sa.title AS answer', 'COUNT(sa.title) AS countAnswers')
				->join('s.question', 'sq')
				->join('sq.groupFields', 'g')
				->join('s.answer', 'sa')
				->join('s.formationParticipant', 'fp')
				->addGroupBy('sq.title')
				->addGroupBy('sa.title')
				->orderBy('g.position', 'ASC')
		);
	}

	public function getStatisticsByFormation(Formation $formation)
	{
		return (
			$this->createStatisticsQueryBuilder()
				->join('fp.formationCourse', 'fc')
				->join('fc.formation', 'f', Join::WITH, 'f = :formation')
				->setParameter('formation', $formation)
				->getQuery()
				->getResult()
		);
	}

	public function getStatisticsByFormationCourse(FormationCourse $formationCourse)
	{
		return (
			$this->createStatisticsQueryBuilder()
				->join('fp.formationCourse', 'fc', Join::WITH, 'fc = :formationCourse')
				->setParameter('formationCourse', $formationCourse)
				->getQuery()
				->getResult()
		);
	}


	/**
	 * @return array
	 */
	public function getResponsesbyMonths($start, $end){

		$qb = $this->createQueryBuilder('s');

		$qb->select('YEAR(s.createdAt) as year')
			->addSelect('MONTH(s.createdAt) as month')
			->addSelect('COUNT(s.id) as total')
			->where('s.createdAt <= :end')
			->andWhere('s.createdAt >= :start')
			->groupBy('year, month')
			->orderBy('year, month')
			->setParameter('start', $start)
			->setParameter('end', $end);

		return $qb->getQuery()->getScalarResult();
	}
}
