<?php

namespace App\Entity;

use App\Repository\PollRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PollRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class Poll extends AbstractEntity
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
	 * @ORM\Column(type="string", length=13, nullable=true)
	 */
	private $quizId;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private $question;

	/**
	 * @ORM\Column(type="integer", nullable=true)
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

	public function getQuizId(): ?string
	{
		return $this->quizId;
	}

	public function setQuizId(?string $quizId): self
	{
		$this->quizId = $quizId;

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

	public function getQuestion(): ?int
	{
		return $this->question;
	}

	public function setQuestion(?int $question): self
	{
		$this->question = $question;

		return $this;
	}

	public function getAnswer(): ?int
	{
		return $this->answer;
	}

	public function setAnswer(?int $answer): self
	{
		$this->answer = $answer;

		return $this;
	}
}
