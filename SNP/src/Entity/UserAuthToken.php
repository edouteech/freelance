<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Repository\UserAuthTokenRepository;

/**
 * @ORM\Entity(repositoryClass=UserAuthTokenRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class UserAuthToken extends AbstractEntity
{
	/**
	 * @ORM\Column(type="string", unique=true)
	 */
	protected $value;

	/**
	 * @ORM\Column(type="datetime")
	 * @var DateTime
	 */
	protected $createdAt;

	/**
	 * @ORM\ManyToOne(targetEntity="User")
	 * @var User
	 */
	protected $user;

	/**
	 * Triggered on insert
	 * @ORM\PrePersist
	 * @throws Exception
	 */
	public function onPrePersist()
	{
		$this->setCreatedAt( new DateTime("now") );
		$this->setValue($this->generateToken());
	}

	public function getValue()
	{
		return $this->value;
	}

	public function setValue($value): self
	{
		$this->value = $value;
		return $this;
	}

	public function getCreatedAt()
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTime $createdAt): self
	{
		$this->createdAt = $createdAt;
		return $this;
	}

	public function getUser() : ?UserInterface
	{
		return $this->user;
	}

	public function setUser(UserInterface $user): self
	{
		$this->user = $user;

		return $this;
	}
}