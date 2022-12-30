<?php

namespace App\Entity;

use App\Repository\FormationParticipantConnectionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=FormationParticipantConnectionRepository::class)
 */
class FormationParticipantConnection extends AbstractEntity
{
    /**
     * @ORM\ManyToOne(targetEntity=FormationParticipant::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $formationParticipant;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $joinAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $leaveAt;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $duration;

    public function getFormationParticipant(): ?FormationParticipant
    {
        return $this->formationParticipant;
    }

    public function setFormationParticipant(?FormationParticipant $formationParticipant): self
    {
        $this->formationParticipant = $formationParticipant;

        return $this;
    }

    public function getJoinAt(): ?\DateTimeInterface
    {
        return $this->joinAt;
    }

    public function setJoinAt(?\DateTimeInterface $joinAt): self
    {
        $this->joinAt = $joinAt;

        return $this;
    }

    public function getLeaveAt(): ?\DateTimeInterface
    {
        return $this->leaveAt;
    }

    public function setLeaveAt(?\DateTimeInterface $leaveAt): self
    {
        $this->leaveAt = $leaveAt;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->formationParticipant->getContact()->getFirstName() ?? null;
    }

    public function getLastName(): ?string
    {
        return $this->formationParticipant->getContact()->getLastName() ?? null;
    }

    public function getMemberId(): ?string
    {
        return $this->formationParticipant->getContact()->getMemberId() ?? null;
    }

    public function getFormationId(): ?int
    {
        return $this->formationParticipant->getFormationCourse()->getFormation()->getId() ?? null;
    }

    public function getFormationName(): ?string
    {
        return $this->formationParticipant->getFormationCourse()->getFormation()->getTitle() ?? null;
    }
}
