<?php

namespace App\Entity;

use App\Repository\ContractDetailsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass=ContractDetailsRepository::class)
 */
class ContractDetails extends AbstractEudoEntity
{
    const RCP_PRODUCT_PRIME_FILEID = '135';
    const RCP_PRODUCT_APPLICATION_FEE_FILEID = '136';
    const RCP_PRODUCT_RENEW_FEE_FILEID = '137';

    const PJ_PRODUCT_PRIME_FILEID = '23';
    const PJ_PRODUCT_APPLICATION_FEE_FILEID = '24';
    const PJ_PRODUCT_RENEW_FEE_FILEID = '24';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $product;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $quantity;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $nonRenewable;

    /**
     * @ORM\ManyToOne(targetEntity=Contact::class)
     * @Groups({"eudonet","insert"})
     */
    private $contact;

    /**
     * @ORM\ManyToOne(targetEntity=Contract::class)
     * @Groups({"eudonet","insert"})
     */
    private $contract;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $prorata;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"eudonet","insert","update"})
     */
    private $unitPrice;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?string
    {
        return $this->product;
    }

    public function setProduct(?string $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getNonRenewable(): ?bool
    {
        return $this->nonRenewable;
    }

    public function setNonRenewable(?bool $nonRenewable): self
    {
        $this->nonRenewable = $nonRenewable;

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

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): self
    {
        $this->contract = $contract;

        return $this;
    }

    public function getProrata(): ?string
    {
        return $this->prorata;
    }

    public function setProrata(?string $prorata): self
    {
        $this->prorata = $prorata;

        return $this;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }
}
