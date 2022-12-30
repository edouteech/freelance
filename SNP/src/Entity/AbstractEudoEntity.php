<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Exception;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @MappedSuperclass
 * @ORM\HasLifecycleCallbacks()
 */
abstract class AbstractEudoEntity extends AbstractEntity
{
	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 * @Groups({"eudonet","insert","update"})
	 */
	protected $id;

	//Todo: generate and use uuid

	/**
	 * @ORM\Column(type="datetime")
	 */
    protected $createdAt;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
    protected $updatedAt;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
    protected $insertedAt;

	public function __construct($id=false)
	{
		if( $id )
			$this->setId($id);
	}

	public function setId($id)
	{
		$this->id = $id;

		return $this;
	}

	public function getUpdatedAt(): ?DateTimeInterface
	{
		return $this->updatedAt;
	}

	/**
	 * @param $updatedAt
	 * @return $this
	 * @throws Exception
	 */
	public function setUpdatedAt($updatedAt): self
	{
		$this->updatedAt = $this->formatDateTime($updatedAt);

		return $this;
	}

	public function getCreatedAt(): ?DateTimeInterface
	{
		return $this->createdAt;
	}

	/**
	 * @param $createdAt
	 * @return $this
	 * @throws Exception
	 */
	public function setCreatedAt($createdAt ): self
	{
		$this->createdAt = $this->formatDateTime($createdAt, true);

		return $this;
	}

	/**
	 * @ORM\PrePersist
	 * @throws Exception
	 */
	public function prePersist()
	{
		$this->setInsertedAt(new DateTime());

		if( is_null($this->getCreatedAt()) )
			$this->setCreatedAt(new DateTime());

		return $this;
	}

	public function getInsertedAt(): ?DateTimeInterface
	{
		return $this->insertedAt;
	}

	public function setInsertedAt( DateTimeInterface $insertedAt ): self
	{
		$this->insertedAt = $insertedAt;

		return $this;
	}
}
