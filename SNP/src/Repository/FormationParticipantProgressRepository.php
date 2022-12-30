<?php

namespace App\Repository;

use App\Entity\FormationParticipantProgress;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method FormationParticipantProgress|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormationParticipantProgress|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormationParticipantProgress[]    findAll()
 * @method FormationParticipantProgress[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationParticipantProgressRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormationParticipantProgress::class);
    }

    /**
     * @param FormationParticipantProgress|null $formationParticipantProgress
     * @param bool $type
     * @return array|null
     */
    public function hydrate(?FormationParticipantProgress $formationParticipantProgress, $type=false){

        if( !$formationParticipantProgress ){

            return [
                'chapter'=>[
                    'current'=>-1,
                    'max'=>-1
                ],
                'subchapter'=>[
                    'current'=>0,
                    'max'=>0
                ],
                'timeElapsed'=>0,
                'scroll'=>0,
                'media'=>0
            ];
        }

        return [
            'chapter'=>[
                'current'=>$formationParticipantProgress->getChapter(),
                'max'=>$formationParticipantProgress->getChapterRead()
            ],
            'subchapter'=>[
                'current'=>$formationParticipantProgress->getSubchapter(),
                'max'=>$formationParticipantProgress->getSubchapterRead()
            ],
            'timeElapsed'=>$formationParticipantProgress->getTimeElapsed(),
            'scroll'=>$formationParticipantProgress->getScroll(),
            'media'=>$formationParticipantProgress->getMedia()
        ];
    }
}
