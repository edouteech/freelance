<?php

namespace App\Entity;

use App\Repository\SurveyQuestionRepository;
use App\DBAL\FormationTypeEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SurveyQuestionRepository::class)
 */
class SurveyQuestion extends AbstractEntity
{
    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

	/**
	 * @ORM\Column(type="smallint", nullable=true)
	 */
	private $position;

    /**
     * @ORM\OneToMany(targetEntity=SurveyAnswer::class, mappedBy="question")
     */
    private $answers;

    /**
     * @ORM\ManyToOne(targetEntity=SurveyQuestionGroup::class, inversedBy="questions")
     * @ORM\JoinColumn(nullable=false)
     */
    private $groupFields;

    /**
     * @ORM\Column(type=FormationTypeEnum::class, nullable=true)
     */
    private $format;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
    }

	public function __toString()
	{
		return $this->getTitle();
	}

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

	public function getPosition(): ?int
         	{
         		return $this->position;
         	}

	public function setPosition(?int $position): self
         	{
         		$this->position = $position;
         
         		return $this;
         	}

    /**
     * @return Collection|SurveyAnswer[]
     */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(SurveyAnswer $answer): self
    {
        if (!$this->answers->contains($answer)) {
            $this->answers[] = $answer;
            $answer->setQuestion($this);
        }

        return $this;
    }

    public function removeAnswer(SurveyAnswer $answer): self
    {
        if ($this->answers->contains($answer)) {
            $this->answers->removeElement($answer);
            // set the owning side to null (unless already changed)
            if ($answer->getQuestion() === $this) {
                $answer->setQuestion(null);
            }
        }

        return $this;
    }

    public function getGroupFields(): ?SurveyQuestionGroup
    {
        return $this->groupFields;
    }

    public function setGroupFields(?SurveyQuestionGroup $groupFields): self
    {
        $this->groupFields = $groupFields;

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;

        return $this;
    }
}
