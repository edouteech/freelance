<?php

namespace App\Repository;

use App\Entity\Registration;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method Registration|null find($id, $lockMode = null, $lockVersion = null)
 * @method Registration|null findOneBy(array $criteria, array $orderBy = null)
 * @method Registration[]    findAll()
 * @method Registration[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegistrationRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, Registration::class, $parameterBag);
    }


	/**
	 * @param Registration|null $registration
	 * @return array|bool
	 */
	public function hydrate(?Registration $registration)
	{
		if( !$registration )
			return false;

		return [
			'information' => $this->formatDate($registration->getInformation()),
			'agencies' => $this->formatDate($registration->getAgencies()),
			'contract' => $this->formatDate($registration->getContract()),
			'payment' => $this->formatDate($registration->getPayment()),
			'validPayment' => $this->formatDate($registration->getValidPayment()),
			'validAsseris' => $this->formatDate($registration->getValidAsseris()),
			'validCaci' => $this->formatDate($registration->getValidCaci())
		];
	}
}
