<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\AgreementRepository;

/**
 * @ORM\Entity(repositoryClass=AgreementRepository::class)
 */
class Agreement extends AbstractEudoEntity
{
    /**
     * @ORM\Column(type="float")
     */
    private $amount;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $number;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $mode;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $generateNumber;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $generateInvoice;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $validateInvoice;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $computeAmounts;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $invoiceId;

    /**
     * @ORM\ManyToOne(targetEntity=Contact::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $contact;

    /**
     * @ORM\ManyToOne(targetEntity=Company::class)
     * @ORM\JoinColumn(nullable=true)
     */
    private $company;

    /**
     * @ORM\ManyToOne(targetEntity=FormationCourse::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $formationCourse;

    /**
     * @ORM\OneToMany(targetEntity=FormationParticipant::class, mappedBy="agreement")
     */
    private $formationParticipants;

    public function __construct($id=false)
    {
        parent::__construct($id);
        $this->formationParticipants = new ArrayCollection();
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount($amount): self
    {
        $this->amount = $this->formatFloat($amount);

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    public function getGenerateNumber(): ?bool
    {
        return $this->generateNumber;
    }

    public function setGenerateNumber(?bool $generateNumber): self
    {
        $this->generateNumber = $generateNumber;

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

    public function getValidateInvoice(): ?bool
    {
        return $this->validateInvoice;
    }

    public function setValidateInvoice(?bool $validateInvoice): self
    {
        $this->validateInvoice = $validateInvoice;

        return $this;
    }

    public function getComputeAmounts(): ?bool
    {
        return $this->computeAmounts;
    }

    public function setComputeAmounts(?bool $computeAmounts): self
    {
        $this->computeAmounts = $computeAmounts;

        return $this;
    }

    public function getInvoiceId(): ?string
    {
        return $this->invoiceId;
    }

    public function setInvoiceId(?string $invoiceId): self
    {
        $this->invoiceId = $invoiceId;

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

    public function setContactId(?int $contact_id): self
    {
        if( $contact_id ){

            $contact = new Contact();
            $contact->setId($contact_id);

            $this->setContact($contact);
        }

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

    public function setCompanyId(?int $company_id): self
    {
        if( $company_id ){

            $company = new Company();
            $company->setId($company_id);

            $this->setCompany($company);
        }

        return $this;
    }

    public function getFormationCourse(): ?FormationCourse
    {
        return $this->formationCourse;
    }

    public function setFormationCourse(?FormationCourse $formationCourse): self
    {
        $this->formationCourse = $formationCourse;

        return $this;
    }    
    
    public function setFormationCourseId(?int $formation_course_id): self
    {
        if( $formation_course_id ){

            $formationCourse = new FormationCourse();
            $formationCourse->setId($formation_course_id);
            $this->setFormationCourse($formationCourse);
        }

        return $this;
    }

    /**
     * @return Collection|FormationParticipant[]
     */
    public function getFormationParticipants(): Collection
    {
        return $this->formationParticipants;
    }

    public function addFormationParticipant(FormationParticipant $formationParticipant): self
    {
        if (!$this->formationParticipants->contains($formationParticipant)) {
            $this->formationParticipants[] = $formationParticipant;
            $formationParticipant->setAgreement($this);
        }

        return $this;
    }

    public function removeFormationParticipant(FormationParticipant $formationParticipant): self
    {
        if ($this->formationParticipants->removeElement($formationParticipant)) {
            // set the owning side to null (unless already changed)
            if ($formationParticipant->getAgreement() === $this) {
                $formationParticipant->setAgreement(null);
            }
        }

        return $this;
    }
}
