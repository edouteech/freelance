<?php

namespace App\Entity;

use App\Repository\SignatoryRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass=SignatoryRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Signatory extends AbstractEntity
{
    /**
     * @ORM\ManyToOne(targetEntity=Address::class)
     */
    private $address;

    /**
     * @ORM\ManyToOne(targetEntity=Signature::class, inversedBy="signatories")
     * @ORM\JoinColumn(nullable=false)
     */
    private $signature;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $status;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $updatedAt;

	/**
	 * @ORM\Column(type="string", length=50)
	 */
	private $transactionId;

	/**
	 * @ORM\PrePersist
	 * @throws Exception
	 */
	public function prePersist()
	{
		$this->setUpdatedAt(new DateTime());

		return $this;
	}

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getSignature(): ?Signature
    {
        return $this->signature;
    }

    public function setSignature(?Signature $signature): self
    {
        $this->signature = $signature;

        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): self
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
