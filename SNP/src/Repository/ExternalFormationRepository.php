<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\ExternalFormation;
use DateTime;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method ExternalFormation|null find($id, $lockMode = null, $lockVersion = null)
 * @method ExternalFormation|null findOneBy(array $criteria, array $orderBy = null)
 * @method ExternalFormation[]    findAll()
 * @method ExternalFormation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ExternalFormationRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, ExternalFormation::class, $parameterBag);
    }


	/**
	 * @param Contact[] $contacts
	 * @return ExternalFormation[]
	 */
	public function getLastFormations($contacts, $date){

		$qb = $this->createQueryBuilder('f')
			->where('f.startAt > :date')
			->setParameter('date', $date);

		$qb->andWhere('f.contact IN (:contacts)')
			->setParameter('contacts', $contacts);

		return $qb->getQuery()->getResult();
	}

	/**
	 * @param ExternalFormation|null $externalFormation
	 * @param bool $type
	 * @return array|bool
	 */
	public function hydrate(?ExternalFormation $externalFormation, $type=false)
	{
		if(!$externalFormation )
			return false;

		$data = [
			'id' => $externalFormation->getId(),
			'format' => $externalFormation->getFormat(),
			'startAt' => $this->formatDate($externalFormation->getStartAt()),
			'endAt' => $this->formatDate($externalFormation->getEndAt()),
			'appendix' => null,
			'external' => true,
			'location' => false,
			'formation'=>[
				'title' => $externalFormation->getTitle(),
				'type' => 'external-formation',
				'duration' => [
					'hours'=>$externalFormation->getHours(),
					'hoursEthics'=>$externalFormation->getHoursEthics(),
					'hoursDiscrimination'=>$externalFormation->getHoursDiscrimination()
				]
			]
		];

		if( $externalFormation->getFormat() != 'e-learning'  ){

			$data['location'] = [
				'latLng'=>false,
				'street' => $externalFormation->getAddress(),
				'zip' => false
			];
		}

		if( $externalFormation->getCertificate() ){

			$data['appendix'] = [
				'id'=>$externalFormation->getId(),
				'title'=>$externalFormation->getTitle(),
				'type'=>'appendix',
				'entity'=>'external-formation',
				'createdAt'=>$this->formatDate($externalFormation->getCreatedAt()),
				'assets'=>[
					[
						'title'=>$externalFormation->getCertificate()
					]
				]
			];
		}

		return $data;
	}
}
