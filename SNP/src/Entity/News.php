<?php

namespace App\Entity;

use App\DBAL\NewsLinkTypeEnum;
use App\DBAL\RoleEnum;
use App\DBAL\FunctionEnum;
use App\DBAL\NewsTargetEnum;
use Swagger\Annotations as SWG;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NewsRepository")
 */
class News extends Resource
{
	protected $type = 'news';

	/**
	 * @SWG\Property(example="https://www.snpi.fr/actu/lorem")
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $link;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $thumbnail;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	protected $label;

	/**
	 * @ORM\Column(type=NewsLinkTypeEnum::class, nullable=true)
	 */
	private $linkType;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $featured;

	/**
	 * @ORM\Column(type=RoleEnum::class)
	 */
	protected $role='ROLE_USER';

	/**
	 * @ORM\Column(type=NewsTargetEnum::class, nullable=true)
	 */
	private $target;

	/**
	 * @ORM\Column(type=FunctionEnum::class, nullable=true, name="`function`")
	 */
	private $function;


	public function getThumbnail(): ?string
	{
		return $this->thumbnail;
	}

	public function setThumbnail(?string $thumbnail): self
	{
		$this->thumbnail = $thumbnail == "0" ? null : $thumbnail;

		return $this;
	}


	public function getRole(): ?string
	{
		return $this->role;
	}

	public function setRole(string $role): self
	{
		$this->role = 'ROLE_'.strtoupper($role);

		return $this;
	}

	public function getLink(): ?string
	{
		return $this->link;
	}

	public function setLink(?string $link): self
	{
		$this->link = $link;

		return $this;
	}

	public function getFunction(): ?string
	{
		return $this->function;
	}

	public function setFunction(?string $function): self
	{
		$this->function = !empty($function)?$function:null;;

		return $this;
	}

	public function getLabel(): ?string
	{
		return $this->label;
	}

	public function setLabel(?string $label): self
	{
		$this->label = $label;

		return $this;
	}

	public function getLinkType(): ?string
	{
		return $this->linkType;
	}

	public function setLinkType(?string $linkType): self
	{
		$this->linkType = $linkType;

		return $this;
	}

	public function getFeatured(): ?bool
	{
		return $this->featured;
	}

	public function setFeatured(?bool $featured): self
	{
		$this->featured = $featured;

		return $this;
	}

	public function getRemoteUrl(): ?string
	{
		return $this->getServerUrl() . '/news/' . $this->getSlug();
	}

	public function getTarget()
	{
		return $this->target;
	}

	public function setTarget($target): self
	{
		$this->target = !empty($target)?$target:null;

		return $this;
	}
}
