<?php

namespace App\Entity;

use App\DBAL\RoleNameEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RatingRepository")
 */
class Rating extends AbstractEntity
{
    /**
     * @ORM\Column(type="float")
     */
    private $rate;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\ManyToOne(targetEntity=Resource::class, inversedBy="ratings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $resource;

    public function __construct()
    {
        $this->resource = new ArrayCollection();
    }

    public function getRate(): ?float
    {
        return $this->rate;
    }

    public function setRate(float $rate): self
    {
        $this->rate = max(0, min(5, $rate));

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->formatText($this->comment);
    }

    public function setComment(?string $comment): self
    {
        $this->comment = substr(filter_var($comment, FILTER_SANITIZE_STRING), 0 , 500);

        return $this;
    }

    public function getResource(): ?Resource
    {
        return $this->resource;
    }

    public function setResource(?Resource $resource): self
    {
        $this->resource = $resource;

        return $this;
    }
}
