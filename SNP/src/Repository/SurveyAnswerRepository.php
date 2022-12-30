<?php

namespace App\Repository;

use App\Entity\SurveyAnswer;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method SurveyAnswer|null find($id, $lockMode = null, $lockVersion = null)
 * @method SurveyAnswer|null findOneBy(array $criteria, array $orderBy = null)
 * @method SurveyAnswer[]    findAll()
 * @method SurveyAnswer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SurveyAnswerRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyAnswer::class);
    }
}
