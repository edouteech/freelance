<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\DBAL\RoleEnum;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PageRepository")
 */
class Page extends Resource
{
	protected $type = 'page';

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $thumbnail;

	/**
	 * @ORM\Column(type=RoleEnum::class)
	 */
	protected $role='ROLE_USER';

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $layout;


	public function getRole(): ?string
	{
		return $this->role;
	}

	public function setRole(string $role): self
	{
		$this->role = 'ROLE_'.strtoupper($role);

		return $this;
	}

    public function getThumbnail(): ?string
    {
    	return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): self
    {
        $this->thumbnail = $thumbnail == "0" ? null : $thumbnail;

        return $this;
    }

	public function getRemoteUrl(): ?string
	{
		return $this->getServerUrl() . '/edito/' . $this->getSlug();
	}

    public function setExcerpt($text){

        $this->setDescription($text);
    }

    public function setLayout(array $layout): self{

        $this->layout = $layout;

        return $this;
    }

    public function getLayout(): array{

        return $this->layout;
    }
}
