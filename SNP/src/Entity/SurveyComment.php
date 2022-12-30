<?php

namespace App\Entity;

use App\Repository\SurveyCommentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=SurveyCommentRepository::class)
 */
class SurveyComment extends AbstractEntity
{
	/**
	 * @ORM\ManyToOne(targetEntity=FormationParticipant::class)
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $formationParticipant;

	/**
	 * @ORM\Column(type="text")
	 */
	private $value;

	public function __toString()
	{
		return $this->getValue();
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

	public function getValue(): ?string
	{
		return $this->value;
	}

	public function setValue(string $value): self
	{
		$this->value = $value;

		return $this;
	}
}
