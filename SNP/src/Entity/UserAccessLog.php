<?php

namespace App\Entity;

use App\Repository\UserAccessLogRepository;
use Doctrine\ORM\Mapping as ORM;
use App\DBAL\UserAccessLogTypeEnum;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass=UserAccessLogRepository::class)
 * @ORM\HasLifecycleCallbacks()
 */
class UserAccessLog extends AbstractEntity
{
    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type=UserAccessLogTypeEnum::class)
     */
    private $type;

    /**
     * @ORM\Column(type="datetime")
     */
    private $occurred_at;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $ipHash;

    /**
     * @ORM\PrePersist
     * @throws \Exception
     */
    public function prePersist()
    {
        $this->setOccurredAt(new \DateTime());

        return $this;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getOccurredAt(): ?\DateTimeInterface
    {
        return $this->occurred_at;
    }

    public function setOccurredAt(\DateTimeInterface $occurred_at): self
    {
        $this->occurred_at = $occurred_at;

        return $this;
    }

    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    public function setIpHash(string $ipHash): self
    {
        $this->ipHash = $ipHash;

        return $this;
    }
}
