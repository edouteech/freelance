<?php

namespace App\Repository;

use App\Entity\FormationCourse;
use App\Entity\FormationInterest;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * @method FormationInterest|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormationInterest|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormationInterest[]    findAll()
 * @method FormationInterest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationInterestRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormationInterest::class);
    }

	/**
	 * @param UserInterface $user
	 * @param FormationCourse $formationCourse
	 * @throws ExceptionInterface
	 * @throws ORMException
	 */
	public function create(UserInterface $user, FormationCourse $formationCourse)
    {
        $interest = new FormationInterest();

        if( $contact = $user->getContact() )
	        $interest->setContact($contact);

	    if( $company = $user->getCompany() )
		    $interest->setCompany($company);

	    $interest->setFormationCourse($formationCourse);

        $interest->setAlert($user->isCollaborator());

        if( $this->findOneBy(['contact'=>$interest->getContact(), 'company'=>$interest->getCompany(), 'formationCourse'=>$interest->getFormationCourse()]) )
        	return;

        $this->save($interest);
	}
	
	public function findByFormationCoursesHaveRemainingPlaces()
	{
		$qb = $this->createQueryBuilder('f');

		return (
			$qb
				->join('f.formationCourse', 'fc')
				->where('f.sendAt IS NULL')
				->andWhere('fc.remainingPlaces > 0')
				->andWhere('f.alert = 0')
				->getQuery()
				->getResult()
		);
	}

}
