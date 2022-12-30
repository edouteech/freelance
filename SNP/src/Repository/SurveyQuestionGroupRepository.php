<?php

namespace App\Repository;

use App\Entity\SurveyQuestionGroup;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method SurveyQuestionGroup|null find($id, $lockMode = null, $lockVersion = null)
 * @method SurveyQuestionGroup|null findOneBy(array $criteria, array $orderBy = null)
 * @method SurveyQuestionGroup[]    findAll()
 * @method SurveyQuestionGroup[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SurveyQuestionGroupRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyQuestionGroup::class);
    }
}
