<?php

namespace App\Repository;

use App\Entity\DocumentAsset;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method DocumentAsset|null find($id, $lockMode = null, $lockVersion = null)
 * @method DocumentAsset|null findOneBy(array $criteria, array $orderBy = null)
 * @method DocumentAsset[]    findAll()
 * @method DocumentAsset[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DocumentAssetRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry, ParameterBagInterface $parameterBag)
    {
        parent::__construct($registry, DocumentAsset::class, $parameterBag);
    }

	/**
	 * @param DocumentAsset|null $asset
	 * @return array
	 */
	public function hydrate(?DocumentAsset $asset)
	{
		if( !$asset )
			return null;

		if( $asset->getIsActive() ){
			return [
				'title' => $asset->getTitle(),
				'description' => $asset->getDescription(),
				'type'  => $asset->getType()
			];
		}

		return null;
	}
}
