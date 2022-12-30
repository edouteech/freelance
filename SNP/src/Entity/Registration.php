<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use App\DBAL\RcpTypeEnum;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\RegistrationRepository;

/**
 * @ORM\Entity(repositoryClass=RegistrationRepository::class)
 */
class Registration
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $information;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $agencies;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $contract;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $payment;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validPayment;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validAsseris;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $validCaci;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $insurerNameRcpAc;

    /**
     * @ORM\Column(type=RcpTypeEnum::class, length=255, nullable=true)
     */
    private $hasAlreadyRcpAc;

    /**
     * @ORM\OneToOne(targetEntity=Contract::class)
     */
    private $contractRCP;

    /**
     * @ORM\OneToOne(targetEntity=Contract::class)
     */
    private $contractPJ;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $membershipId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInformation(): ?DateTimeInterface
    {
        return $this->information;
    }

    public function setInformation(bool $state): self
    {
    	$state = $state?new DateTime():null;
        $this->information = $state;

        return $this;
    }

    public function getAgencies(): ?DateTimeInterface
    {
        return $this->agencies;
    }

    public function setAgencies(bool $state): self
    {
	    $state = $state?new DateTime():null;
	    $this->agencies = $state;

        return $this;
    }

    public function getPayment(): ?DateTimeInterface
    {
        return $this->payment;
    }

    public function setPayment(bool $state): self
    {
	    $state = $state?new DateTime():null;
	    $this->payment = $state;

        return $this;
    }

    public function getValidPayment(): ?DateTimeInterface
    {
        return $this->validPayment;
    }

    public function setValidPayment(bool $state): self
    {
	    $state = $state?new DateTime():null;
	    $this->validPayment = $state;

        return $this;
    }

    public function getValidCaci(): ?DateTimeInterface
    {
        return $this->validCaci;
    }

    public function setValidCaci(bool $state): self
    {
	    $state = $state?new DateTime():null;
	    $this->validCaci = $state;

        return $this;
    }

    public function getValidAsseris(): ?DateTimeInterface
    {
        return $this->validAsseris;
    }

    public function setValidAsseris(bool $state): self
    {
	    $state = $state?new DateTime():null;
	    $this->validAsseris = $state;

        return $this;
    }

    public function getContract(): ?DateTimeInterface
    {
        return $this->contract;
    }

    public function setContract(bool $state): self
    {
	    $state = $state?new DateTime():null;
	    $this->contract = $state;

        return $this;
    }

    public function getInsurerNameRcpAc(): ?string
    {
        return $this->insurerNameRcpAc;
    }

    public function setInsurerNameRcpAc(?string $insurerNameRcpAc): self
    {
        $this->insurerNameRcpAc = $insurerNameRcpAc;

        return $this;
    }

    public function getHasAlreadyRcpAc(): ?string
    {
        return $this->hasAlreadyRcpAc;
    }

    public function setHasAlreadyRcpAc(string $hasAlreadyRcpAc): self
    {
        $this->hasAlreadyRcpAc = $hasAlreadyRcpAc;

        return $this;
    }

    public function getNomAssureurRcp(): ?string
    {
        return $this->nomAssureurRcp;
    }

    public function setNomAssureurRcp(string $nomAssureurRcp): self
    {
        $this->nomAssureurRcp = $nomAssureurRcp;

        return $this;
    }

    public function getRegistrationFolderName()
    {
        return sprintf('DA%d', $this->id);
    }

    public function getContractRCP(): ?Contract
    {
        return $this->contractRCP;
    }

    public function setContractRCP(?Contract $contractRCP): self
    {
        $this->contractRCP = $contractRCP;

        return $this;
    }

    public function getContractPJ(): ?Contract
    {
        return $this->contractPJ;
    }

    public function setContractPJ(?Contract $contractPJ): self
    {
        $this->contractPJ = $contractPJ;

        return $this;
    }

    public function getMembershipId(): ?int
    {
        return $this->membershipId;
    }

    public function setMembershipId(?int $membershipId): self
    {
        $this->membershipId = $membershipId;

        return $this;
    }
}
