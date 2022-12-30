<?php

namespace App\Entity;

use App\Repository\SurveyRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SurveyRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Survey extends AbstractEntity
{
    /**
     * @ORM\ManyToOne(targetEntity=FormationParticipant::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $formationParticipant;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity=SurveyQuestion::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $question;

    /**
     * @ORM\ManyToOne(targetEntity=SurveyAnswer::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $answer;


	/**
	 * @ORM\PrePersist
	 */
	public function prePersist()
	{
		if( is_null($this->getCreatedAt()) )
			$this->setCreatedAt(new DateTime());

		return $this;
	}

    public function getFormationParticipant(): ?FormationParticipant
    {
        return $this->formationParticipant;
    }

    public function setFormationParticipant(?FormationParticipant $formationParticipant): self
    {
        $this->formationParticipant = $formationParticipant;

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

    public function getQuestion(): ?SurveyQuestion
    {
        return $this->question;
    }

    public function setQuestion(?SurveyQuestion $question): self
    {
        $this->question = $question;

        return $this;
    }

    public function getAnswer(): ?SurveyAnswer
    {
        return $this->answer;
    }

    public function setAnswer(?SurveyAnswer $answer): self
    {
        $this->answer = $answer;

        return $this;
    }
}
