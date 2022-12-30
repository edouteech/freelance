<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\MailRepository")
 */
class Mail extends AbstractEudoEntity
{
	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $street1;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $street2;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $street3;

	/**
	 * @ORM\Column(type="string", length=50, nullable=true)
	 */
	private $zip;

	/**
	 * @ORM\Column(type="string", length=100, nullable=true)
	 */
	private $district;

	/**
	 * @ORM\Column(type="string", length=100, nullable=true)
	 */
	private $city;

	/**
	 * @ORM\Column(type="string", length=100, nullable=true)
	 */
	private $country;

	/**
	 * @ORM\Column(type="text", nullable=true)
	 */
	private $note;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Company", inversedBy="mails")
	 * @ORM\JoinColumn(nullable=false)
	 */
	private $company;

	public function getStreet1(): ?string
	{
		return $this->street1;
	}

	public function setStreet1(?string $street1): self
	{
		$this->street1 = $this->formatStreet($street1);

		return $this;
	}

	public function getStreet2(): ?string
	{
		return $this->street2;
	}

	public function setStreet2(?string $street2): self
	{
		$this->street2 = $this->formatStreet($street2);

		return $this;
	}

	public function getStreet3(): ?string
	{
		return $this->street3;
	}

	public function setStreet3(?string $street3): self
	{
		$this->street3 = $this->formatStreet($street3);

		return $this;
	}

	public function getStreet(string $glue="\n"): ?string
	{
		if( $this->getStreet1() || $this->getStreet2() || $this->getStreet3() ){

			$street = array_filter([$this->getStreet1(), $this->getStreet2(), $this->getStreet3()]);
			return implode($glue, $street);
		}

		return NULL;
	}

	public function setStreet(?string $street, string $glue="\n"): self
	{
		if( $street ){

			$street = explode($glue, $street);

			if( count($street) >= 1)
				$this->setStreet1($street[0]);

			if( count($street) >= 2)
				$this->setStreet2($street[1]);

			if( count($street) >= 3)
				$this->setStreet3($street[2]);
		}

		return $this;
	}

	public function getZip(): ?string
	{
		return $this->zip;
	}

	public function setZip(?string $zip): self
	{
		$this->zip = $zip;

		return $this;
	}

	public function getDistrict(): ?string
	{
		return $this->district;
	}

	public function setDistrict(?string $district): self
	{
		$this->district = $district;

		return $this;
	}

	public function getNote(): ?string
	{
		return $this->note;
	}

	public function setNote(?string $note): self
	{
		$this->note = $note;

		return $this;
	}

	public function getCity(): ?string
	{
		return $this->city;
	}

	public function setCity(?string $city): self
	{
		$this->city = $this->formatCity($city);

		return $this;
	}

	public function getCountry(): ?string
	{
		return $this->country;
	}

	public function setCountry(?string $country): self
	{
		$this->country = $country;

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

			$this->setCompany($company);
		}

		return $this;
	}
}
