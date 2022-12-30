<?php

namespace App\Entity;

use DateTime;
use Exception;
use DateTimeInterface;
use Swagger\Annotations as SWG;
use Doctrine\ORM\Mapping as ORM;
use App\DBAL\DocumentAssetTypeEnum;
use JMS\Serializer\Annotation as Serializer;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DocumentAssetRepository")
 */
class DocumentAsset extends AbstractEntity
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=100)
     */
    protected $id;

    /**
     * @SWG\Property(example="editable-pdf")
     * @ORM\Column(type=DocumentAssetTypeEnum::class)
     */
    private $type;

    /**
     * @SWG\Property(example="http://www.snpi.pro/docs/mandat-sn221a.pdf")
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $url;

    /**
     * @SWG\Property(example="Mandat exclusif de vente")
     * @ORM\Column(type="string", length=255)
     */
    private $title;

    /**
     * @SWG\Property(example="N221A")
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $description;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Document", inversedBy="resources")
     * @Serializer\Exclude()
     */
    private $document;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $modifiedAt;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isActive=1;

    public function setId(string $id): self
    {
        $this->id = $id;

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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDocument(): ?Document
    {
        return $this->document;
    }

    public function setDocument(?Document $document): self
    {
        $this->document = $document;

        return $this;
    }

    public function getModifiedAt(): ?DateTimeInterface
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(DateTimeInterface $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function setModified(?string $modified): self
    {
        if( $modified ){

            try {
                $modifiedAt = new DateTime("@" . $modified);
                $this->setModifiedAt($modifiedAt);
            } catch (Exception $e) {
            }
        }

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->getIsActive();
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive($isActive): self
    {
        $this->isActive = $this->formatBool($isActive);

        return $this;
    }

    public function setActive(?bool $isActive): self
    {
        $this->setIsActive($isActive);

        return $this;
    }
}
