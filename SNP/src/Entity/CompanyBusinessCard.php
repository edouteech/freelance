<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CompanyBusinessCardRepository")
 */
class CompanyBusinessCard extends AbstractEudoEntity
{
	/**
	 * @ORM\Column(type="string", length=100, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $number;

	/**
	 * @ORM\Column(type="date", nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $issuedAt;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Company", inversedBy="businessCards")
	 * @Groups({"eudonet","insert"})
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $company;

	/**
	 * @ORM\Column(type="boolean")
	 * @Groups({"eudonet","insert"})
	 */
	private $isActive;

	/**
	 * @ORM\Column(type="date", nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $expireAt;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $cci;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $kind;


	public function getNumber(): ?string
	{
		return $this->number;
	}

	public function setNumber(?string $number): self
	{
		$this->number = $number;

		return $this;
	}

	public function getIssuedAt(): ?DateTimeInterface
	{
		return $this->issuedAt;
	}

	/**
	 * @param $issuedAt
	 * @return $this
	 * @throws Exception
	 */
	public function setIssuedAt($issuedAt): self
	{
		$this->issuedAt = $this->formatDateTime($issuedAt);

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

	public function setCompanyId(?int $company_id): self
	{
		if( $company_id ){

			$company = new Company();
			$company->setId($company_id);

			$this->company = $company;
		}

		return $this;
	}

	public function getIsActive(): ?bool
	{
		return $this->isActive;
	}

	public function isActive(): ?bool
	{
		return $this->getIsActive();
	}

	public function setIsActive($isActive): self
	{
		$this->isActive = $this->formatBool($isActive);

		return $this;
	}

	public function getExpireAt(): ?DateTimeInterface
	{
		return $this->expireAt;
	}

	/**
	 * @param $expireAt
	 * @return $this
	 * @throws Exception
	 */
	public function setExpireAt($expireAt): self
	{
		$this->expireAt = $this->formatDateTime($expireAt);

		return $this;
	}

	public function getCci(): ?string
	{
		return $this->cci;
	}

	public function setCci(?string $cci): self
	{
		$this->cci = $cci;

		return $this;
	}

	public function getKind(): ?array
	{
		if( $this->kind ){

			$kinds = array_filter(explode(';', $this->kind));
			return array_map('trim', $kinds);
		}

		return $this->kind;
	}

	public function setKind($kind): self
	{
		if( is_array($kind ) )
			$this->kind = implode(';', $kind);
		else
			$this->kind = $kind;

		return $this;
	}
}
