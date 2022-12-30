<?php
namespace App\Entity;

use DateTime;
use DateTimeInterface;
use App\DBAL\PaymentStatusEnum;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Payum\Core\Model\Payment as BasePayment;
use App\Repository\PaymentRepository;

/**
 * @ORM\Table
 * @ORM\Entity(repositoryClass=PaymentRepository::class)
 */
class Payment extends BasePayment
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $street;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $city;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $zip;

    /**
     * @ORM\Column(type="string", length=3)
     */
    private $countryCode;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $totalTax;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $refundAmount;

    /**
     * @ORM\Column(type=PaymentStatusEnum::class)
     */
    private $status="new";

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $updatedAt;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $returnUrl;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $errorUrl;

    /**
     * @ORM\ManyToOne(targetEntity=Order::class, inversedBy="payments")
     */
    private $order;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $reference;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $tpe;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $entity;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isDeposit;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     */
    private $user;


    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setStreet(?string $street): self
    {
        if( !$street )
            throw new Exception('Address is incomplete');

        $this->street = $street;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * @throws Exception
     */
    public function setCity(?string $city): self
    {
        if( !$city )
            throw new Exception('Address is incomplete');

        $this->city = $city;

        return $this;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    /**
     * @throws Exception
     */
    public function setZip(?string $zip): self
    {
        if( !$zip )
            throw new Exception('Address is incomplete');

        $this->zip = $zip;

        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): self
    {
        if( !$countryCode ) $countryCode = 'fr';

        $this->countryCode = strtoupper(substr($countryCode, 0 ,2));

        return $this;
    }

    public function getTotalTax(): ?int
    {
        return $this->totalTax;
    }

    public function setTotalTax(?int $totalTax): self
    {
        $this->totalTax = $totalTax;

        return $this;
    }

    public function getRefundAmount(): ?int
    {
        return $this->refundAmount;
    }

    public function setRefundAmount(?int $refundAmount): self
    {
        $this->refundAmount = $refundAmount;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        $this->setUpdatedAt(new DateTime());

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

    public function getReturnUrl(): ?string
    {
        return $this->returnUrl;
    }

    public function setReturnUrl(?string $returnUrl): self
    {
        $this->returnUrl = $returnUrl;

        return $this;
    }

    public function getErrorUrl(): ?string
    {
        return empty($this->errorUrl)?$this->returnUrl:$this->errorUrl;
    }

    public function setErrorUrl(?string $errorUrl): self
    {
        $this->errorUrl = $errorUrl;

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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getTpe(): ?string
    {
        return $this->tpe;
    }

    public function setTpe(?string $tpe): self
    {
        $this->tpe = $tpe;

        return $this;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(?string $entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function getIsDeposit(): ?bool
    {
        return $this->isDeposit;
    }

    public function setIsDeposit(?bool $isDeposit): self
    {
        $this->isDeposit = $isDeposit;

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
}