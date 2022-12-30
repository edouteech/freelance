<?php

namespace App\Entity;

use App\Repository\StatisticsRepository;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=StatisticsRepository::class)
 */
class Statistics extends AbstractEntity
{
    /**
     * @ORM\Column(type="datetime")
     */
    private $lastCalledAt;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $path;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $category;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $method;

    /**
     * @ORM\Column(type="integer")
     */
    private $count;

    public function getLastCalledAt(): ?DateTimeInterface
    {
        return $this->lastCalledAt;
    }

    public function setLastCalledAt(DateTimeInterface $lastCalledAt): self
    {
        $this->lastCalledAt = $lastCalledAt;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }
}
