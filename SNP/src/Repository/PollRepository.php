<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\FormationCourse;
use App\Entity\FormationParticipant;
use App\Entity\Poll;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method Poll|null find($id, $lockMode = null, $lockVersion = null)
 * @method Poll|null findOneBy(array $criteria, array $orderBy = null)
 * @method Poll[]    findAll()
 * @method Poll[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PollRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Poll::class);
    }

    /**
     * @param $quizId
     * @param $answer
     * @param $question
     * @param FormationParticipant $formationParticipant
     * @param array $questions
     * @throws ExceptionInterface
     * @throws ORMException
     */
	public function create($quizId, $answer, $question, FormationParticipant $formationParticipant, $questions){

		$quiz = [];

		foreach ($questions as $data)
			$quiz[$data['name']] = $data['answers'];


		if( !($quiz[$question]??false) )
			return;

		if( !in_array($answer, $quiz[$question]) )
			return;

		//since the beginning answer and question have been inverted
        //todo: make patch
        $pollAnswer = array_search($question, array_keys($quiz));
        $pollQuestion = array_search($answer, $quiz[$question]);

        if(!$poll = $this->findOneBy(['quizId'=>$quizId, 'answer'=>$pollAnswer, 'formationParticipant'=>$formationParticipant])){

            $poll = new Poll();

            $poll->setQuizId($quizId);
            $poll->setAnswer($pollAnswer);
            $poll->setFormationParticipant($formationParticipant);
        }

        $poll->setQuestion($pollQuestion);

		$this->save($poll);
	}


    /**
     * @param FormationCourse $formationCourse
     * @param Contact|null $contact
     * @return Poll[]
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


    /**
     * @param FormationCourse $formationCourse
     * @param array $criteria
     * @return array
     */
    public function export(FormationCourse $formationCourse, $criteria=[]){

        if( !$polls = $this->findByFormationCourse($formationCourse, $criteria['contact']??null) )
            return [];

        if( !$quiz = $formationCourse->getFormation()->getFoad()->getQuiz() )
			return [];

        $header = [
            'Titre',
            'Date',
            'Participant',
            'Question',
            'Réponse',
            '',
        ];

        $rows = [];

        array_push( $rows, implode(';', $header) );

        foreach ($polls as $poll) {

            if( !$question = ($quiz[$poll->getAnswer()]??false) )
                continue;

            $answers = $question['answers']??[];

	        $rows[] = implode(
		        ';', [
			        $formationCourse->getFormation()->getTitle(),
			        $formationCourse->getStartAt()->format('d-m-Y'),
			        $poll->getFormationParticipant()->getContact(),
			        $question['name'],
			        ($answers[$poll->getQuestion()]??''),
			        ''
		        ]
	        );
        }

        return $rows;
    }


	/**
	 * @param FormationParticipant $formationParticipant
	 * @return int|mixed|string
	 * @throws Exception
	 */
	public function deleteBy(FormationParticipant $formationParticipant){

		$qb = $this->createQueryBuilder('p');

		$qb->delete()
			->where('p.formationParticipant = :formationParticipant')
			->setParameter('formationParticipant', $formationParticipant);

		$query = $qb->getQuery();

		return $query->getResult();
	}


	/**
	 * @return array
	 */
	public function getResponsesbyMonths($start, $end){


		$qb = $this->createQueryBuilder('p');

		$qb->select('YEAR(p.createdAt) as year')
			->addSelect('MONTH(p.createdAt) as month')
			->addSelect('COUNT(p.id) as total')
			->where('p.createdAt <= :end')
			->andWhere('p.createdAt >= :start')
			->groupBy('year, month')
			->orderBy('year, month')
			->setParameter('start', $start)
			->setParameter('end', $end);

		return $qb->getQuery()->getScalarResult();
	}
}
