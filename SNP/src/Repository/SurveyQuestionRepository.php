<?php

namespace App\Repository;

use App\Entity\SurveyQuestion;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method SurveyQuestion|null find($id, $lockMode = null, $lockVersion = null)
 * @method SurveyQuestion|null findOneBy(array $criteria, array $orderBy = null)
 * @method SurveyQuestion[]    findAll()
 * @method SurveyQuestion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SurveyQuestionRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyQuestion::class);
    }
}
