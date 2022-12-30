<?php

namespace App\Entity;

use App\Repository\SurveyAnswerRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SurveyAnswerRepository::class)
 */
class SurveyAnswer extends AbstractEntity
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
     * @ORM\ManyToOne(targetEntity=SurveyQuestion::class, inversedBy="answers")
     */
    private $question;

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

    public function getQuestion(): ?SurveyQuestion
    {
        return $this->question;
    }

    public function setQuestion(?SurveyQuestion $question): self
    {
        $this->question = $question;

        return $this;
    }
}
