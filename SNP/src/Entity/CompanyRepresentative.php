<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CompanyRepresentativeRepository")
 */
class CompanyRepresentative extends AbstractEudoEntity
{
	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Company", inversedBy="legalRepresentatives")
	 * @ORM\JoinColumn(nullable=false)
	 * @Groups({"eudonet","insert"})
	 */
	private $company;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Contact", inversedBy="legalRepresentatives")
	 * @ORM\JoinColumn(nullable=false)
	 * @Groups({"eudonet","insert"})
	 */
	private $contact;

	/**
	 * @ORM\Column(type="boolean")
	 * @Groups({"eudonet","insert"})
	 */
	private $archived=0;

	/**
     * @ORM\Column(type="string", length=255, nullable=true)
	 */
	private $label;

	public function setContactId(?int $contact_id): self
	{
		if( $contact_id ){

			$contact = new Contact();
			$contact->setId($contact_id);

			$this->setContact($contact);
		}

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

	public function getArchived(): ?bool
	{
		return $this->archived;
	}

	public function isArchived(): ?bool
	{
		return $this->getArchived();
	}

	public function setArchived($archived): self
	{
		$this->archived = $this->formatBool($archived);

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

	public function getLabel(): ?string
	{
		return $this->label;
	}

	public function setLabel(?string $label): self
	{
		$this->label = $this->formatString($label);

		return $this;
	}
}
