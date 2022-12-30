<?php

namespace App\Entity;

use App\Repository\SignatureRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass=SignatureRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Signature extends AbstractEntity
{
    /**
     * @ORM\Column(type="string", length=150)
     */
    private $transactionId;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $entity;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $createdAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $expiredAt;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $account;

    /**
     * @ORM\Column(type="integer")
     */
    private $count;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $entityId;

    /**
     * @ORM\OneToMany(targetEntity=Signatory::class, mappedBy="signature", orphanRemoval=true)
     */
    private $signatories;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $fileUploaded;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $alertedAt;

	/**
	 * @ORM\PrePersist
	 * @throws Exception
	 */
	public function prePersist()
	{
		$this->setCreatedAt(new DateTime());

		return $this;
	}

    public function __construct()
    {
        $this->signatories = new ArrayCollection();
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(string $transactionId): self
    {
        $this->transactionId = $transactionId;

        return $this;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(?string $entity): self
    {
        $this->entity = $entity;

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

    public function getFileUploaded(): ?bool
    {
        return $this->fileUploaded;
    }

    public function setFileUploaded(?bool $fileUploaded): self
    {
        $this->fileUploaded = $fileUploaded;

        return $this;
    }

    public function getAlertedAt(): ?DateTimeInterface
    {
        return $this->alertedAt;
    }

    public function setAlertedAt(?DateTimeInterface $alertedAt): self
    {
        $this->alertedAt = $alertedAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiredAt(): ?DateTimeInterface
    {
        return $this->expiredAt;
    }

    public function setExpiredAt($expiredAt): self
    {
	    if( $expiredAt instanceof DateTimeInterface){

		    $this->expiredAt = $expiredAt;
	    }
	    elseif( is_string($expiredAt) ){

	    	$createdAt = $this->getCreatedAt();
	    	$createdAt->modify($expiredAt);

	    	$this->expiredAt = $createdAt;
	    }

        return $this;
    }

    public function getAccount(): ?string
    {
        return $this->account;
    }

    public function setAccount(string $account): self
    {
        $this->account = $account;

        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    /**
     * @return Collection|Signatory[]
     */
    public function getSignatories(): Collection
    {
        return $this->signatories;
    }

    public function addSignatory(Signatory $signatory): self
    {
        if (!$this->signatories->contains($signatory)) {
            $this->signatories[] = $signatory;
            $signatory->setSignature($this);
        }

        return $this;
    }

    public function removeSignatory(Signatory $signatory): self
    {
        if ($this->signatories->contains($signatory)) {
            $this->signatories->removeElement($signatory);
            // set the owning side to null (unless already changed)
            if ($signatory->getSignature() === $this) {
                $signatory->setSignature(null);
            }
        }

        return $this;
    }
}
