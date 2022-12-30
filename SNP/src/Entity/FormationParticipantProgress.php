<?php

namespace App\Entity;

use App\Repository\FormationParticipantProgressRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=FormationParticipantProgressRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class FormationParticipantProgress extends AbstractEntity
{
    /**
     * @ORM\PrePersist
     */
    public function prePersist()
    {
        $this->setUpdatedAt(new DateTime());

        return $this;
    }

    /**
     * @ORM\Column(type="integer")
     */
    private $chapter=-1;

    /**
     * @ORM\Column(type="integer")
     */
    private $chapterRead=-1;

    /**
     * @ORM\Column(type="integer")
     */
    private $subchapter=0;

    /**
     * @ORM\Column(type="integer")
     */
    private $subchapterRead=0;

    /**
     * @ORM\Column(type="integer")
     */
    private $timeElapsed;

    /**
     * @ORM\Column(type="float")
     */
    private $scroll;

    /**
     * @ORM\Column(type="float")
     */
    private $media;

    /**
     * @ORM\OneToOne(targetEntity=FormationParticipant::class, inversedBy="progress", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $formationParticipant;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $updatedAt;

    public function __toArray()
    {
        return [
            'chapter'=>$this->getChapter(),
            'subchapter'=>$this->getSubchapter(),
            'timeElapsed'=>$this->getTimeElapsed(),
            'scroll'=>$this->getScroll(),
            'media'=>$this->getMedia()
        ];
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt($updatedAt): self
    {
        $this->updatedAt = $this->formatDateTime($updatedAt);

        return $this;
    }

    public function getChapter(): ?int
    {
        return $this->chapter;
    }


    public function setChapter(int $chapter): self
    {
        $this->chapter = $chapter;

        if( $this->getChapterRead() < $chapter )
            $this->setChapterRead($chapter);

        return $this;
    }

    public function getScroll(): ?float
    {
        return $this->scroll;
    }

    public function setScroll(float $scroll): self
    {
        $this->scroll = $scroll;

        return $this;
    }

    public function getMedia(): ?float
    {
        return $this->media;
    }

    public function setMedia(float $media): self
    {
        $this->media = $media;

        return $this;
    }

    public function getCompleted(): ?bool
    {
        return $this->getMedia() >= 1;
    }

    public function getSubchapter(): ?int
    {
        return $this->subchapter;
    }

    public function setSubchapter(int $subchapter): self
    {
        $this->subchapter = $subchapter;

        if( $this->getSubchapterRead() < $subchapter )
            $this->setSubchapterRead($subchapter);

        return $this;
    }

    public function getSubchapterRead(): ?int
    {
        return $this->subchapterRead;
    }

    public function setSubchapterRead(int $subchapterRead): self
    {
        $this->subchapterRead = $subchapterRead;

        return $this;
    }

    public function setChapterRead(int $chapterRead): self
    {
        $this->chapterRead = $chapterRead;

        return $this;
    }

    public function getChapterRead(): ?int
    {
        return $this->chapterRead;
    }

    public function getTimeElapsed(): ?int
    {
        return $this->timeElapsed;
    }

    public function setTimeElapsed(int $timeElapsed): self
    {
        $this->timeElapsed = $timeElapsed;

        return $this;
    }

    public function getFormationParticipant(): ?FormationParticipant
    {
        return $this->formationParticipant;
    }

    public function setFormationParticipant(FormationParticipant $formationParticipant): self
    {
        $this->formationParticipant = $formationParticipant;

        return $this;
    }
}
