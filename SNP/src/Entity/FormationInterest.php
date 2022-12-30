<?php

namespace App\Entity;

use App\Repository\FormationInterestRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * @ORM\Entity(repositoryClass=FormationInterestRepository::class)
 * @Table(uniqueConstraints={@UniqueConstraint(name="interest", columns={"contact_id","formation_course_id"})})
 * @ORM\HasLifecycleCallbacks()
 */
class FormationInterest extends AbstractEntity
{
    /**
     * @ORM\ManyToOne(targetEntity=Contact::class, inversedBy="formationInterests")
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
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\Column(type="boolean")
     */
    private $alert;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $sendAt;

	/**
	 * @ORM\PrePersist
	 */
	public function prePersist()
         	{
         		if( is_null($this->getCreatedAt()) )
         			$this->setCreatedAt(new DateTime());
         
         		return $this;
         	}

	public function getAlert(): ?bool
         	{
         		return $this->alert;
         	}

	public function setAlert($alert): self
         	{
         		$this->alert = $this->formatBool($alert);
         
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

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;

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

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getSendAt(): ?DateTimeInterface
    {
        return $this->sendAt;
    }

    public function setSendAt(?DateTimeInterface $sendAt): self
    {
        $this->sendAt = $sendAt;

        return $this;
    }
}
