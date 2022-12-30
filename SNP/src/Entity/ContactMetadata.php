<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping as ORM;
use App\DBAL\ContactMetadataTypeEnum;
use App\DBAL\ContactMetadataStateEnum;
use Doctrine\ORM\Mapping\UniqueConstraint;
use JMS\Serializer\Annotation as Serializer;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ContactMetadataRepository")
 * @Table(uniqueConstraints={@UniqueConstraint(name="resource_idx", columns={"state","contact_id","entity_id","type"})})
 * @ORM\HasLifecycleCallbacks()
 */
class ContactMetadata extends AbstractEntity
{
    /**
     * @ORM\Column(type=ContactMetadataStateEnum::class)
     */
    private $state;

    /**
     * @ORM\Column(type="datetime")
     */
    private $date;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Contact", inversedBy="contactMetadata")
     * @ORM\JoinColumn(nullable=false)
     * @Serializer\Exclude()
     */
    private $contact;

    /**
     * @ORM\Column(type="string", length=20)
     */
    private $entityId;

    /**
     * @ORM\Column(type=ContactMetadataTypeEnum::class)
     */
    private $type;


	/**
	 * Triggered on insert
	 * @ORM\PrePersist
	 */
	public function onPrePersist()
	{
		$this->setDate( new DateTime("now") );
	}

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getDate(): ?DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function getEntityId()
    {
        return is_numeric($this->entityId)?intval($this->entityId):$this->entityId;
    }

    public function setEntityId(?string $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
