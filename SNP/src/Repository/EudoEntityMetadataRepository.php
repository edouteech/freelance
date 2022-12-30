<?php

namespace App\Repository;

use App\Entity\AbstractEudoEntity;
use App\Entity\EudoEntityMetadata;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\ORMException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;


/**
 * @method EudoEntityMetadata|null find($id, $lockMode = null, $lockVersion = null)
 * @method EudoEntityMetadata|null findOneBy(array $criteria, array $orderBy = null)
 * @method EudoEntityMetadata[]    findAll()
 * @method EudoEntityMetadata[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EudoEntityMetadataRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EudoEntityMetadata::class);
    }

	/**
	 * @param AbstractEudoEntity $entity
	 * @param $data
	 * @return EudoEntityMetadata
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function create(AbstractEudoEntity $entity, $data=false){

	    if( !$metadata = $this->findByEntity($entity) ){

            $metadata = new EudoEntityMetadata();
            $metadata->setEntity(get_class($entity));
            $metadata->setEntityId($entity->getId());

            if( $data )
	            $metadata->setData($data);

            $this->save($metadata);
        }

	    return $metadata;
    }

	/**
	 * @param AbstractEudoEntity $entity
	 * @param $data
	 * @throws ORMException
	 * @throws ExceptionInterface
	 */
	public function update(AbstractEudoEntity $entity, $data){

	    if( $metadata = $this->findByEntity($entity) ){

            $metadata->setData($data);
            $this->save($metadata);
        }
    }

	/**
	 * @param AbstractEudoEntity $entity
	 * @return EudoEntityMetadata|null
	 */
	public function findByEntity(AbstractEudoEntity $entity){

    	return $this->findOneBy(['entity'=>str_replace('Proxies\__CG__\\', '', get_class($entity)), 'entityId'=>$entity->getId()]);
    }
}
