<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OrderDetailRepository")
 */
class OrderDetail extends AbstractEntity
{
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Order", inversedBy="details")
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $order;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private $productId;

	/**
	 * @ORM\Column(type="float")
	 */
	private $price;

	/**
	 * @ORM\Column(type="integer")
	 */
	private $quantity;

	/**
	 * @ORM\Column(type="float")
	 */
	private $taxRate;

	/**
	 * @ORM\Column(type="integer")
	 */
	private $quantityInStock=-1;

	/**
	 * @ORM\ManyToMany(targetEntity="App\Entity\Contact")
	 */
	private $contacts;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $processed=false;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private $processedStep=0;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $title;

	/**
	 * @ORM\Column(type="json", nullable=true)
	 */
	private $description;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private $paymentId;


	public function __construct()
	{
		$this->contacts = new ArrayCollection();
	}

	public function getPaymentId(): ?int
	{
		return $this->paymentId;
	}

	public function setPaymentId(?int $paymentId): self
	{
		$this->paymentId = $paymentId;

		return $this;
	}

	public function getProcessedStep(): ?int
	{
		return $this->processedStep;
	}

	public function setProcessedStep(int $processedStep): self
	{
		$this->processedStep = $processedStep;

		return $this;
	}

	public function getProcessed(): ?bool
	{
		return $this->processed;
	}

	public function setProcessed($processed): self
	{
		$this->processed = $this->formatBool($processed);

		return $this;
	}

	public function getOrder(): ?Order
	{
		return $this->order;
	}

	public function setOrder(?Order $order): self
	{
		$this->order = $order;

		return $this;
	}

	public function getProductId(): ?int
	{
		return $this->productId;
	}

	public function setProductId(int $productId): self
	{
		$this->productId = $productId;

		return $this;
	}

	public function getPrice(): ?float
	{
		return $this->price;
	}

	public function getPriceWithTaxes(): ?float
	{
		return $this->price*(1+$this->getTaxRate());
	}

	public function setPrice($price): self
	{
		$this->price = floatval($price);

		return $this;
	}

	public function getQuantity(): ?int
	{
		return $this->quantity;
	}

	public function setQuantity(int $quantity): self
	{
		$this->quantity = $quantity;

		return $this;
	}

	public function getTaxRate(): ?float
	{
		return $this->taxRate;
	}

	public function setTaxRate(float $taxRate): self
	{
		$this->taxRate = $taxRate;

		return $this;
	}

	public function getQuantityInStock(): ?int
	{
		return $this->quantityInStock;
	}

	public function setQuantityInStock(int $quantityInStock): self
	{
		$this->quantityInStock = $quantityInStock;

		return $this;
	}

	/**
	 * @return Collection|Contact[]
	 */
	public function getContacts(): Collection
	{
		return $this->contacts;
	}

	public function addContact(Contact $contact): self
	{
		if (!$this->contacts->contains($contact)) {
			$this->contacts[] = $contact;
		}

		return $this;
	}

	public function addContacts($contacts): self
	{
        $this->contacts = new ArrayCollection();

		if( !$contacts )
			return $this;

		foreach ($contacts as $contact)
			$this->addContact($contact);

		return $this;
	}

	public function removeContact(Contact $contact): self
	{
		if ($this->contacts->contains($contact)) {
			$this->contacts->removeElement($contact);
		}

		return $this;
	}

	public function getTitle(): ?string
	{
		return $this->title;
	}

	public function setTitle(?string $title): self
	{
		$this->title = $title;

		return $this;
	}

	public function getDescription()
	{
		return $this->description;
	}

	public function setDescription($description): self
	{
		$this->description = $description;

		return $this;
	}
}
