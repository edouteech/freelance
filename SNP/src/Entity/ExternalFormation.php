<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ExternalFormationRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class ExternalFormation extends AbstractEntity
{
	/**
	 * @ORM\Column(type="string", length=255)
	 */
	private $title;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $address;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private $startAt;

	/**
	 * @ORM\Column(type="datetime")
	 */
	private $endAt;

	/**
	 * @ORM\Column(type="string", columnDefinition="ENUM('instructor-led','in-house','e-learning')", nullable=true)
	 */
	private $format;

	/**
	 * @ORM\Column(type="integer")
	 */
	private $hours;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private $hoursEthics;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private $hoursDiscrimination;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $certificate;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Contact")
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $contact;

	/**
	 * @ORM\Column(type="datetime")
	 */
	private $createdAt;

	/**
	 * @ORM\PrePersist
	 * @throws Exception
	 */
	public function prePersist()
	{
		if( is_null($this->getCreatedAt()) )
			$this->setCreatedAt(new DateTime());

		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setTitle(string $title): self
	{
		$this->title = $title;

		return $this;
	}

	public function getAddress(): ?string
	{
		return $this->address;
	}

	public function setAddress(?string $address): self
	{
		$this->address = $address;

		return $this;
	}

	public function getStartAt(): ?DateTimeInterface
	{
		return $this->startAt;
	}

	public function setStartAt(?DateTimeInterface $startAt): self
	{
		$this->startAt = $startAt;

		return $this;
	}

	public function getEndAt(): ?DateTimeInterface
	{
		return $this->endAt;
	}

	public function setEndAt(DateTimeInterface $endAt): self
	{
		$this->endAt = $endAt;

		return $this;
	}

	public function getFormat(): ?string
	{
		return $this->format;
	}

	public function setFormat(string $format): self
	{
		$this->format = $format;

		return $this;
	}

	public function getHours(): ?int
	{
		return $this->hours;
	}

	public function setHours(int $hours): self
	{
		$this->hours = $hours;

		return $this;
	}

	public function getHoursEthics(): ?float
	{
		return $this->hoursEthics;
	}

	public function setHoursEthics(?float $hoursEthics): self
	{
		$this->hoursEthics = $hoursEthics;

		return $this;
	}

	public function getHoursDiscrimination(): ?float
	{
		return $this->hoursDiscrimination;
	}

	public function setHoursDiscrimination(?float $hoursDiscrimination): self
	{
		$this->hoursDiscrimination = $hoursDiscrimination;

		return $this;
	}

	public function getCertificate(): ?string
	{
		return $this->certificate;
	}

	public function setCertificate(?string $certificate): self
	{
		$this->certificate = $certificate;

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

	public function getCreatedAt(): ?DateTimeInterface
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTimeInterface $createdAt): self
	{
		$this->createdAt = $createdAt;

		return $this;
	}
}
