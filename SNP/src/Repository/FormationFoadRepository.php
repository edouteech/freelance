<?php

namespace App\Repository;

use App\Entity\Formation;
use App\Entity\FormationFoad;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method FormationFoad|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormationFoad|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormationFoad[]    findAll()
 * @method FormationFoad[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationFoadRepository extends AbstractRepository
{
	public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
	{
		parent::__construct($registry, FormationFoad::class, $parameterBag);
	}

    public function hydrate(?FormationFoad $formationFoad, $type=false)
    {
        if( !$formationFoad )
            return null;

        $formation = $formationFoad->getFormation();

        if( $type == self::$HYDRATE_FULL ){

            $pdf_filename = $this->getPath('formation_directory').'/'.$formation->getId().'.pdf';

            return [
                'quiz' => $formationFoad->getQuiz(),
                'video' => $formationFoad->getVideo(),
                'write' => $formationFoad->getWrite(),
                'documents' => $formationFoad->getDocuments(),
                'driveFileId' => $formationFoad->getDriveFileId(),
                'pdf' => file_exists($pdf_filename)
            ];
        }
        else{

            if( $type == Formation::FORMAT_WEBINAR )
                return $formationFoad->getQuiz();
            elseif( $type == Formation::FORMAT_E_LEARNING )
                return $formationFoad->getWrite();
        }

        return null;
    }
}
