<?php

namespace App\Repository;

use App\Entity\ContactMetadata;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method ContactMetadata|null find($id, $lockMode = null, $lockVersion = null)
 * @method ContactMetadata|null findOneBy(array $criteria, array $orderBy = null)
 * @method ContactMetadata[]    findAll()
 * @method ContactMetadata[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContactMetadataRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, ContactMetadata::class, $parameterBag);
    }

	public function hydrate(ContactMetadata $meta)
	{
		return [
			'entityId' => $meta->getEntityId(),
			'type' => $meta->getType(),
			'state' => $meta->getState(),
			'date' => $this->formatDate($meta->getDate())
		];
	}
}
