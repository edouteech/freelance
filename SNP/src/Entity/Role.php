<?php

namespace App\Entity;

use App\DBAL\RoleNameEnum;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RoleRepository")
 */
class Role extends AbstractEntity
{
	/**
	 * @ORM\Column(type=RoleNameEnum::class)
	 */
	private $name;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $description;

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;

		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(?string $description): self
	{
		$this->description = $description;

		return $this;
	}


}
