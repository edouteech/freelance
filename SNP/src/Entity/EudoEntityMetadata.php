<?php

namespace App\Entity;

use App\Repository\EudoEntityMetadataRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=EudoEntityMetadataRepository::class)
 */
class EudoEntityMetadata extends AbstractEntity
{
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $entity;

    /**
     * @ORM\Column(type="integer")
     */
    private $entityId;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $data;

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): self
    {
        $this->entity = str_replace('Proxies\__CG__\\', '', $entity);

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getData($key=false)
    {
    	if( $key )
    		return $this->data[$key]??false;

        return $this->data;
    }

    public function setData($data, $value='use_data'): self
    {
    	if( $value === 'use_data' ){

            $this->data = $data;
        }
    	else{

            if( !is_array($this->data) )
                $this->data = [];

            $this->data[$data] = $value;
        }

        return $this;
    }
}
