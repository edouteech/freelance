<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FormationParticipantRepository")
 */
class FormationParticipant extends AbstractEudoEntity
{
    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $present;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $registered;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $absent;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $confirmed;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Contact", inversedBy="participants")
     * @ORM\JoinColumn(nullable=true)
     */
    private $contact;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\FormationCourse", inversedBy="participants")
     * @ORM\JoinColumn(nullable=false)
     */
    private $formationCourse;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Address")
     */
    private $address;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $note;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $registrantId;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $survey;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $poll;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $revived;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $resendMail;

    /**
     * @ORM\OneToOne(targetEntity=FormationParticipantProgress::class, mappedBy="formationParticipant")
     */
    private $progress;

    /**
     * @ORM\ManyToOne(targetEntity=Agreement::class, inversedBy="formationParticipants")
     */
    private $agreement;

    public function getRegistered(): ?bool
    {
        return $this->registered;
    }

    public function setRegistered($registered): self
    {
        $this->registered = $this->formatBool($registered, true);

        return $this;
    }

    public function getResendMail(): ?bool
    {
        return $this->resendMail;
    }

    public function setResendMail($resendMail): self
    {
        $this->resendMail = $this->formatBool($resendMail);

        return $this;
    }

    public function getPresent(): ?bool
    {
        return $this->present;
    }

    public function setPresent($present): self
    {
        $this->present = $this->formatBool($present, true);

        return $this;
    }

    public function getAbsent(): ?bool
    {
        return $this->absent;
    }

    public function setAbsent($absent): self
    {
        $this->absent = $this->formatBool($absent, true);

        return $this;
    }

    public function getConfirmed(): ?bool
    {
        return $this->confirmed;
    }

    public function setConfirmed($confirmed): self
    {
        $this->confirmed = $this->formatBool($confirmed, true);

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

    public function getAddress(): ?Address
    {
	    /** @var Contact $contact */
	    $contact = $this->contact;

    	if( !$this->address && $contact && $contact->isMember() )
    		return $contact->getHomeAddress();

        return $this->address;
    }

    public function setAddress(?Address $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function setAddressId(?int $address_id): self
    {
        if( $address_id ){

            $address = new Address();
            $address->setId($address_id);
            $this->setAddress($address);
        }

        return $this;
    }



    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function getRegistrantId(): ?string
    {
        return $this->registrantId;
    }

    public function setRegistrantId(?string $registrantId): self
    {
        $this->registrantId = $registrantId;

        return $this;
    }

    public function getSurvey(): ?bool
    {
        return $this->survey;
    }

    public function setSurvey($survey): self
    {
        $this->survey = $this->formatBool($survey);

        return $this;
    }

    public function getPoll(): ?bool
    {
        return $this->poll;
    }

    public function setPoll($poll): self
    {
        $this->poll = $this->formatBool($poll);

        return $this;
    }

    public function getRevived(): ?bool
    {
        return $this->revived;
    }

    public function setRevived($revived): self
    {
        $this->revived = $this->formatBool($revived);

        return $this;
    }

    public function getProgress(): ?FormationParticipantProgress
    {
        return $this->progress;
    }

    public function setProgress(FormationParticipantProgress $progress): self
    {
        // set the owning side of the relation if necessary
        if ($progress->getFormationParticipant() !== $this) {
            $progress->setFormationParticipant($this);
        }

        $this->progress = $progress;

        return $this;
    }

    public function getAgreement(): ?Agreement
    {
        return $this->agreement;
    }

    public function setAgreement(?Agreement $agreement): self
    {
        $this->agreement = $agreement;

        return $this;
    }

    public function setAgreementId(?int $agreement_id): self
    {
        if( $agreement_id ){

            $agreement = new Agreement();
            $agreement->setId($agreement_id);

            $this->setAgreement($agreement);
        }

        return $this;
    }
}
