<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Swagger\Annotations as SWG;

/**
 * @ORM\Entity(repositoryClass="App\Repository\DocumentRepository")
 */
class Document extends Resource
{
	protected $type = 'document';

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\DocumentAsset", mappedBy="document", orphanRemoval=true, cascade={"persist","merge"})
	 */
	protected $assets;

	/**
	 * @SWG\Property(example="https://www.snpi.pro/docs/mandat-sn221a.jpg")
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $thumbnail;


	public function __construct()
	{
		parent::__construct();
		$this->assets = new ArrayCollection();
	}

	public function getThumbnail(): ?string
	{
		return $this->thumbnail == "0" ? false : $this->thumbnail;
	}

	public function setThumbnail(?string $thumbnail): self
	{
		$this->thumbnail = $thumbnail;

		return $this;
	}

	public function getAsset(int $position=1): ?DocumentAsset
	{
		return count($this->assets) >= $position ? $this->assets[$position-1] : NULL;
	}

	/**
	 * @return Collection|DocumentAsset[]
	 */
	public function getAssets(): Collection
	{
		return $this->assets;
	}

	public function addAsset($asset): self
	{
		if( is_array($asset) )
			$asset = $this->denormalize($asset, DocumentAsset::class);

		if ($asset instanceof DocumentAsset && !$this->assets->contains($asset)) {
			$this->assets[] = $asset;
			$asset->setDocument($this);
		}

		return $this;
	}

	public function addAssets(array $assets): self
	{
		foreach ($assets as $asset)
			$this->addAsset($asset);

		return $this;
	}

	public function removeAsset(DocumentAsset $asset): self
	{
		if ($this->assets->contains($asset)) {

			$this->assets->removeElement($asset);

			if ($asset->getDocument() === $this)
				$asset->setDocument(null);
		}

		return $this;
	}
}
