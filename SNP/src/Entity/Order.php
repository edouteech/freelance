<?php

namespace App\Entity;

use DateTime;
use Exception;
use DateTimeInterface;
use App\DBAL\OrderTypeEnum;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OrderRepository")
 * @ORM\Table(name="`order`")
 * @ORM\HasLifecycleCallbacks()
 */
class Order extends AbstractEntity
{
	/**
	 * @ORM\Column(type=OrderTypeEnum::class)
	 */
	private $type;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $message;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $error;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $processed=false;

	/**
	 * @ORM\Column(type="string", length=16)
	 */
	private $ip;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private $updatedAt;

	/**
	 * @ORM\Column(type="datetime")
	 */
	private $createdAt;

	/**
	 * @ORM\Column(type="string", length=13, nullable=true)
	 */
	private $invoice;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\OrderDetail", mappedBy="order", orphanRemoval=true, cascade={"persist","merge"})
	 */
	private $details;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Company")
	 * @ORM\JoinColumn(nullable=true)
	 */
	private $company;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Contact")
	 * @ORM\JoinColumn(nullable=true)
	 */
	private $contact;


	/**
	 * @ORM\Column(type="string", length=50, nullable=true)
	 */
	private $Gateway;

	/**
	 * @ORM\OneToMany(targetEntity=Payment::class, mappedBy="order")
	 */
	private $payments;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private $paymentId;


	public function __construct()
	{
		$this->details = new ArrayCollection();
		$this->payments = new ArrayCollection();
	}

	/**
	 * @ORM\PrePersist
	 * @throws Exception
	 */
	public function prePersist()
	{
		$this->setCreatedAt(new DateTime());

		$time = round(microtime(true)*100);
		$this->setInvoice($time);

		return $this;
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

	public function getType(): ?string
	{
		return $this->type;
	}

	public function setType(string $type): self
	{
		$this->type = $type;

		return $this;
	}

	public function getError(): ?bool
	{
		return $this->error;
	}

	public function setError($error): self
	{
		$this->error = $this->formatBool($error);

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

	public function getMessage(): ?string
	{
		return $this->message;
	}

	public function setMessage(?string $message): self
	{
		$this->message = $message;

		return $this;
	}

	public function getIp(): ?string
	{
		return $this->ip;
	}

	public function setIp(string $ip): self
	{
		$this->ip = $ip;

		return $this;
	}

	public function setUser(UserInterface $user): self
	{
		$this->setContact($user->getContact());
		$this->setCompany($user->getCompany());

		return $this;
	}

	public function getUpdatedAt(): ?DateTimeInterface
	{
		return $this->updatedAt;
	}

	public function setUpdatedAt(?DateTimeInterface $updatedAt): self
	{
		$this->updatedAt = $updatedAt;

		return $this;
	}

	public function getCreatedAt(): ?DateTimeInterface
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTimeInterface $createdAt): self
	{
		$this->createdAt = $createdAt;

		return $this;
	}

	public function getInvoice(): ?string
	{
		return $this->invoice;
	}

	public function setInvoice(?string $invoice): self
	{
		$this->invoice = substr($invoice,0, 12);

		return $this;
	}

	public function getTotalAmount(): float
	{
		$total = 0;

		foreach ($this->getDetails() as $detail)
			$total += $detail->getPrice()*$detail->getQuantity();

		return $total;
	}

	public function getTotalTax(): float
	{
		$total = 0;

		foreach ($this->getDetails() as $detail)
			$total += $detail->getPrice()*$detail->getQuantity()*$detail->getTaxRate();

		return $total;
	}

	/**
	 * @return Collection|OrderDetail[]
	 */
	public function getDetails(): Collection
	{
		return $this->details;
	}

	/**
	 * @param $index
	 * @return OrderDetail|bool
	 */
	public function getDetail(int $index)
	{
		$orderDetails = $this->getDetails();

		if( count($orderDetails) > $index )
			return $orderDetails[$index];

		return false;
	}

	public function addDetail(OrderDetail $detail): self
	{
		if (!$this->details->contains($detail)) {
			$this->details[] = $detail;
			$detail->setOrder($this);
		}

		return $this;
	}

	public function removeDetail(OrderDetail $detail): self
	{
		if ($this->details->contains($detail)) {
			$this->details->removeElement($detail);
			// set the owning side to null (unless already changed)
			if ($detail->getOrder() === $this) {
				$detail->setOrder(null);
			}
		}

		return $this;
	}

	public function getCompany(): ?Company
	{
		return $this->company;
	}

	public function setCompany(?Company $company): self
	{
		$this->company = $company;

		return $this;
	}

	public function getContact(): ?Contact
	{
		return $this->contact;
	}

	public function setContact(?Contact $contact): self
	{
		$this->contact = $contact;

		return $this;
	}

	public function getGateway(): ?string
	{
		return $this->Gateway;
	}

	public function setGateway(?string $Gateway): self
	{
		$this->Gateway = $Gateway;

		return $this;
	}

	/**
	 * @return Payment|false
	 */
	public function getPayment()
	{
		foreach ($this->payments as $payment){

			if( $payment->getStatus() == 'captured' )
				return $payment;
		}

		return false;
	}

	public function getPayments(): Collection
	{
		return $this->payments;
	}

	public function addPayment(Payment $payment): self
	{
		if (!$this->payments->contains($payment)) {
			$this->payments[] = $payment;
			$payment->setOrder($this);
		}

		return $this;
	}

	public function removePayment(Payment $payment): self
	{
		if ($this->payments->contains($payment)) {
			$this->payments->removeElement($payment);
			// set the owning side to null (unless already changed)
			if ($payment->getOrder() === $this) {
				$payment->setOrder(null);
			}
		}

		return $this;
	}
}
