<?php

namespace App\Entity;

use App\Repository\ContractRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass=ContractRepository::class)
 */
class Contract extends AbstractEudoEntity
{
    /**
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $amount;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $category;

    /**
     * @ORM\ManyToOne(targetEntity=Company::class)
     * @Groups({"eudonet","insert"})
     */
    private $company;

    /**
     * @ORM\ManyToOne(targetEntity=Contact::class)
     * @Groups({"eudonet","insert"})
     */
    private $contact;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $entity;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $generateInvoice;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $invoice;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert","update"})
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $insurer;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $nonRenewable;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $policyNumber;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $paymentMethod;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $web;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $startDate;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $endDate;

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount($amount): self
    {
        $this->amount = $this->formatFloat($amount);

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;

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

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(?string $entity): self
    {
        $this->entity = strtolower($entity);

        return $this;
    }

    public function getGenerateInvoice(): ?bool
    {
        return $this->generateInvoice;
    }

    public function setGenerateInvoice(?bool $generateInvoice): self
    {
        $this->generateInvoice = $generateInvoice;

        return $this;
    }

    public function getInvoice(): ?string
    {
        return $this->invoice;
    }

    public function setInvoice(?string $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        switch ($status){

            case 'Validé': $status = 'validated'; break;
            case 'En attente': $status = 'pending'; break;
            case 'Abandonné': $status = 'abandonned'; break;
        }

        $this->status = $status;

        return $this;
    }

    public function getInsurer(): ?string
    {
        return $this->insurer;
    }

    public function setInsurer(?string $insurer): self
    {
        $this->insurer = strtolower($insurer);

        return $this;
    }

    public function getnonRenewable(): ?bool
    {
        return $this->nonRenewable;
    }

    public function setnonRenewable(?bool $nonRenewable): self
    {
        $this->nonRenewable = $nonRenewable;

        return $this;
    }

    public function getPolicyNumber(): ?string
    {
        return $this->policyNumber;
    }

    public function setPolicyNumber(?string $policyNumber): self
    {
        $this->policyNumber = $policyNumber;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): self
    {
        switch ($paymentMethod){

            case 'Carte bancaire': $paymentMethod = 'credit_card'; break;
        }

        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getWeb(): ?bool
    {
        return $this->web;
    }

    public function setWeb(?bool $web): self
    {
        $this->web = $web;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }
}
