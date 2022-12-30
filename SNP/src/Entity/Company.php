<?php

namespace App\Entity;

use Combodo\DoctrineEncryptBundle\Configuration\Encrypted;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CompanyRepository")
 */
class Company extends AbstractEudoEntity
{
	/**
	 * @ORM\Column(type="string", length=255)
	 * @Groups({"eudonet","insert"})
	 */
	private $name;

	/**
	 * @ORM\Column(type="string", length=20, nullable=true)
	 */
	private $memberId;

	/**
	 * @ORM\Column(type="string", nullable=true)
     * @Groups({"eudonet","update"})
	 * @Encrypted
	 */
	private $email;
	private $emailDecrypted;


	/**
	 * @ORM\Column(type="string", nullable=true)
	 */
	private $password;

	/**
	 * @ORM\Column(type="string", length=50, nullable=true)
	 */
	private $status;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $isFranchise=0;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $street1;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $street2;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $street3;

	/**
	 * @ORM\Column(type="string", length=10, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $zip;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $city;

	/**
	 * @ORM\Column(type="string", length=50, nullable=true)
	 */
	private $country;

	/**
	 * @ORM\Column(type="string", length=15, nullable=true)
	 * @Groups({"eudonet","update"})
	 */
	private $phone;

	/**
	 * @ORM\Column(type="string", length=15, nullable=true)
	 */
	private $fax;

	/**
	 * @ORM\Column(type="float", nullable=true)
	 */
	private $lat;

	/**
	 * @ORM\Column(type="float", nullable=true)
	 */
	private $lng;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $brand;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","update"})
	 */
	private $website;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","update"})
	 */
	private $logo;

	/**
	 * @ORM\Column(type="string", length=100, nullable=true)
	 */
	private $ape;

	/**
	 * @ORM\Column(type="string", length=20, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $siren;

	/**
	 * @ORM\Column(type="string", length=10, nullable=true)
	 */
	private $nic;

	/**
	 * @ORM\Column(type="string", length=100, nullable=true)
	 */
	private $software;

	/**
	 * @ORM\Column(type="boolean")
	 */
	private $isHidden=0;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 */
	private $acheterLouerId;

	/**
	 * @ORM\Column(type="boolean")
	 */
	private $isEstateManager=0;

	/**
	 * @ORM\Column(type="boolean")
	 */
	private $isDealer=0;

	/**
	 * @ORM\Column(type="boolean")
	 */
	private $isPropertyManager=0;

	/**
	 * @ORM\Column(type="boolean")
	 */
	private $isExpert=0;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private $registeredAt;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\CompanyBusinessCard", mappedBy="company", orphanRemoval=true)
	 */
	private $businessCards;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","update"})
	 */
	private $facebook;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","update"})
	 */
	private $twitter;

	/**
	 * @ORM\Column(type="string", length=50, nullable=true)
	 */
	private $legalForm;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\Mail", mappedBy="company", orphanRemoval=true)
	 */
	private $mails;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","update"})
	 */
	private $adomosKey;

	/**
	 * @ORM\Column(type="boolean")
	 */
	private $hasDashboard=0;

	/**
	 * @ORM\Column(type="boolean")
	 */
	private $canCreateAccount=0;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\CompanyRepresentative", mappedBy="company", orphanRemoval=true)
	 * @var CompanyRepresentative[] $legalRepresentatives
	 */
	private $legalRepresentatives;

	/**
	 * @ORM\Column(type="integer", nullable=true)
	 * @Groups({"eudonet","update"})
	 */
	private $sales;

	/**
	 * @ORM\Column(type="string", length=50, nullable=true)
	 * @Groups({"eudonet","update"})
	 */
	private $turnover;

    /**
     * @Groups({"eudonet","insert"})
     */
    private $kind;

	public function __construct()
	{
		parent::__construct();

		$this->businessCards = new ArrayCollection();
		$this->mails = new ArrayCollection();
		$this->legalRepresentatives = new ArrayCollection();
	}

	public function getHasDashboard(): ?bool
	{
		return $this->hasDashboard;
	}

	public function setHasDashboard($hasDashboard): self
	{
		$this->hasDashboard = $this->formatBool($hasDashboard);

		return $this;
	}

	public function getCanCreateAccount(): ?bool
	{
		return $this->canCreateAccount;
	}

	public function setCanCreateAccount($canCreateAccount): self
	{
		$this->canCreateAccount = $this->formatBool($canCreateAccount);

		return $this;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $this->formatString($name);

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

	public function getLatLng(): ?array
	{
		if( $this->lat == 0 && $this->lng==0 )
			return NULL;
		else
			return [$this->lat, $this->lng];
	}

	public function setLatLng(?string $lat_lng): self
	{
		list($this->lat, $this->lng) = $this->formatPoint($lat_lng);

		return $this;
	}

	public function getLat(): ?float
	{
		return floatval($this->lat);
	}

	public function setLat(?float $lat): self
	{
		$this->lat = $lat;

		return $this;
	}

	public function getLng(): ?float
	{
		return floatval($this->lng);
	}

	public function setLng(?float $lng): self
	{
		$this->lng = $lng;

		return $this;
	}

	public function getMemberId(): ?string
	{
		return $this->memberId;
	}

	public function isMember(): ?string
	{
		return $this->memberId && $this->getStatus() == 'member';
	}

	public function setMemberId(?string $memberId): self
	{
		$this->memberId = $memberId;

		return $this;
	}

	public function getPassword(): ?string
	{
		return $this->password;
	}

	public function setPassword(?string $password): self
	{
		$this->password = $password;

		return $this;
	}

	public function getStatus(): ?string
	{
		return $this->status;
	}

	public function setStatus(?string $status): self
	{
		$this->status = $this->formatStatus($status);

		return $this;
	}

	public function isFranchise(): ?bool
	{
		return $this->getIsFranchise();
	}

	public function getIsFranchise(): ?bool
	{
		return $this->isFranchise;
	}

	public function setIsFranchise($isFranchise): self
	{
		$this->isFranchise = $this->formatBool($isFranchise);

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

	public function getZip(): ?string
	{
		return $this->zip;
	}

	public function setZip($zip): self
	{
		$this->zip = $zip;

		return $this;
	}

	public function getCountry(): ?string
	{
		return $this->country;
	}

	public function setCountry($country): self
	{
		if( !is_null($country) )
			$this->country = $country;

		return $this;
	}

	public function getBrand(): ?string
	{
		return stripslashes($this->brand);
	}

	public function setBrand(?string $brand): self
	{
		$this->brand = $this->formatString($brand);

		return $this;
	}

	public function getWebsite(): ?string
	{
		return $this->website;
	}

	public function setWebsite(?string $website): self
	{
		$this->website = $this->formatUrl($website);

		return $this;
	}

	public function getPhone(): ?string
	{
		return $this->phone;
	}

	public function setPhone(?string $phone): self
	{
		$this->phone = $this->formatPhone($phone);

		return $this;
	}

	public function getFax(): ?string
	{
		return $this->fax;
	}

	public function setFax(?string $fax): self
	{
		$this->fax = $this->formatPhone($fax);

		return $this;
	}

	public function getApe(): ?string
	{
		return $this->ape;
	}

	public function setApe(?string $ape): self
	{
		$this->ape = substr($ape,0,5);

		return $this;
	}

	public function getSiren(): ?string
	{
		return $this->siren;
	}

	public function setSiren($siren): self
	{
		if( !is_null($siren) )
			$this->siren = str_replace(' ','', $siren);

		return $this;
	}

	public function getNic(): ?string
	{
		return $this->nic;
	}

	public function setNic(?string $nic): self
	{
		$this->nic = $nic;

		return $this;
	}

	public function setCategories(?string $categories): self
	{
        $this->isEstateManager = $this->isDealer = $this->isPropertyManager = $this->isExpert = 0;

		if( is_string($categories) ){

			$tab_cat = explode(';', trim($categories));

			foreach($tab_cat as $cat) {
                switch (trim($cat)) {
                    case 'Gestion' :
                        $this->isEstateManager = 1;
                        break;
                    case 'Transaction' :
                        $this->isDealer = 1;
                        break;
                    case 'Syndic' :
                        $this->isPropertyManager = 1;
                        break;
                    case 'Experts' :
                        $this->isExpert = 1;
                        break;
                }
            }
		}
		return $this;
	}

	public function getCategories(): ?array
	{
        $categories = [];

        if( $this->isEstateManager ) $categories[] = 'Gestion';
        if( $this->isDealer ) $categories[] = 'Transaction';
        if( $this->isPropertyManager ) $categories[] = 'Syndic';
        if( $this->isExpert ) $categories[] = 'Experts';

        return $categories;
	}

	public function getSoftware(): ?string
	{
		return $this->software;
	}

	public function setSoftware(?string $software): self
	{
		$this->software = $software;

		return $this;
	}

	public function getEmail(): ?string
	{
		if (!empty($this->emailDecrypted))
			return $this->emailDecrypted;

		return $this->email;
	}

	public function setEmail(?string $email): self
	{
		$this->email = $email;
		$this->emailDecrypted = null;

		return $this;
	}

	public function isHidden(): ?bool
	{
		return $this->getIsHidden();
	}

	public function getIsHidden(): ?bool
	{
		return $this->isHidden;
	}

	public function setIsHidden($isHidden): self
	{
		$this->isHidden = is_null($isHidden) ? false : $isHidden;

		return $this;
	}

	public function getRegisteredAt(): ?DateTimeInterface
	{
		return $this->registeredAt;
	}

	/**
	 * @param $registeredAt
	 * @return $this
	 * @throws Exception
	 */
	public function setRegisteredAt($registeredAt): self
	{
		$this->registeredAt = $this->formatDateTime($registeredAt, false);

		return $this;
	}

	public function getLogo(): ?string
	{
		return $this->logo;
	}

	public function setLogo($logo): self
	{
		$this->logo = $logo;

		return $this;
	}

	public function getAcheterLouerId(): ?int
	{
		return $this->acheterLouerId;
	}

	public function setAcheterLouerId(?int $acheterLouerId): self
	{
		$this->acheterLouerId = $acheterLouerId;

		return $this;
	}

	public function isEstateManager(): ?bool
	{
		return $this->getIsEstateManager();
	}

	public function getIsEstateManager(): ?bool
	{
		return $this->isEstateManager;
	}

	public function setIsEstateManager($isEstateManager): self
	{
		$this->isEstateManager = $this->formatBool($isEstateManager);

		return $this;
	}

	public function isDealer(): ?bool
	{
		return $this->getIsDealer();
	}

	public function getIsDealer(): ?bool
	{
		return $this->isDealer;
	}

	public function setIsDealer($isDealer): self
	{
		$this->isDealer = $this->formatBool($isDealer);

		return $this;
	}

	public function isPropertyManager(): ?bool
	{
		return $this->getIsPropertyManager();
	}

	public function getIsPropertyManager(): ?bool
	{
		return $this->isPropertyManager;
	}

	public function setIsPropertyManager($isPropertyManager): self
	{
		$this->isPropertyManager = $this->formatBool($isPropertyManager);

		return $this;
	}

	public function isExpert(): ?bool
	{
		return $this->getIsExpert();
	}

	public function getIsExpert(): ?bool
	{
		return $this->isExpert;
	}

	public function setIsExpert($isExpert): self
	{
		$this->isExpert = $this->formatBool($isExpert);

		return $this;
	}

	public function getBusinessCard(): ?CompanyBusinessCard
	{
		/** @var CompanyBusinessCard $businessCard */
		foreach ($this->businessCards as $businessCard ){

			if( $businessCard->isActive() )
				return $businessCard;
		}

		return NULL;
	}

	/**
	 * @return Collection|CompanyBusinessCard[]
	 */
	public function getBusinessCards(): Collection
	{
		return $this->businessCards;
	}

	public function addBusinessCard(CompanyBusinessCard $businessCard): self
	{
		if (!$this->businessCards->contains($businessCard)) {
			$this->businessCards[] = $businessCard;
			$businessCard->setCompany($this);
		}

		return $this;
	}

	public function removeBusinessCard(CompanyBusinessCard $businessCard): self
	{
		if ($this->businessCards->contains($businessCard)) {
			$this->businessCards->removeElement($businessCard);
			// set the owning side to null (unless already changed)
			if ($businessCard->getCompany() === $this) {
				$businessCard->setCompany(null);
			}
		}

		return $this;
	}

	public function setEmailDecrypted(?string $emailDecrypted) : self
	{
		$this->emailDecrypted = $emailDecrypted;
		return $this;
	}

	public function getEmailDecrypted() : ?string
	{
		return $this->emailDecrypted;
	}

	public function getFacebook(): ?string
	{
		return $this->facebook;
	}

	public function setFacebook(?string $facebook): self
	{
		$this->facebook = $this->formatUrl($facebook);

		return $this;
	}

	/**
	 * @return array
	 */
	public function getFunctions()
	{
		$functions = [];

		if ( $this->isExpert() )
			$functions[] = 'expert';

		return array_unique($functions);
	}

	public function getTwitter(): ?string
	{
		return $this->twitter;
	}

	public function setTwitter(?string $twitter): self
	{
		$this->twitter = $this->formatUrl($twitter);

		return $this;
	}

	public function getLegalForm(): ?string
	{
		return $this->legalForm;
	}

	public function setLegalForm(?string $legalForm): self
	{
		$this->legalForm = $legalForm;

		return $this;
	}

	/**
	 * @return Collection|Mail[]
	 */
	public function getMails(): Collection
	{
		return $this->mails;
	}

	public function addMail(Mail $mail): self
	{
		if (!$this->mails->contains($mail)) {
			$this->mails[] = $mail;
			$mail->setCompany($this);
		}

		return $this;
	}

	public function removeMail(Mail $mail): self
	{
		if ($this->mails->contains($mail)) {
			$this->mails->removeElement($mail);
			// set the owning side to null (unless already changed)
			if ($mail->getCompany() === $this) {
				$mail->setCompany(null);
			}
		}

		return $this;
	}

	public function getAdomosKey(): ?string
	{
		return $this->adomosKey;
	}

	public function setAdomosKey(?string $adomosKey): self
	{
		$this->adomosKey = $adomosKey;

		return $this;
	}

	/**
	 * @return CompanyRepresentative[]
	 */
	public function getCompanyRepresentatives()
	{
		return $this->legalRepresentatives;
	}

	public function getCompanyRepresentative() : ?CompanyRepresentative
	{
		$companyRepresentative = $this->getCompanyRepresentatives();
		return $companyRepresentative[0]??null;
	}

	/**
	 * @return Contact[]
	 */
	public function getLegalRepresentatives()
	{
		$contacts = [];

		foreach ($this->legalRepresentatives as $legalRepresentative){

            $contact = $legalRepresentative->getContact();

			if( $contact && !$legalRepresentative->isArchived() )
				$contacts[] = $contact;
		}

		return $contacts;
	}

	public function getLegalRepresentative() : ?Contact
	{
		$legalRepresentatives = $this->getLegalRepresentatives();
		return $legalRepresentatives[0]??null;
	}

	public function addLegalRepresentative($legalRepresentative): self
	{
		if( $legalRepresentative instanceof Contact ){

			$companyRepresentive = new CompanyRepresentative();
			$companyRepresentive->setContact($legalRepresentative);
			$legalRepresentative = $companyRepresentive;
		}

		if (!$this->legalRepresentatives->contains($legalRepresentative)) {
			$this->legalRepresentatives[] = $legalRepresentative;
			$legalRepresentative->setCompany($this);
		}

		return $this;
	}

	public function removeLegalRepresentative(CompanyRepresentative $legalRepresentative): self
	{
		if ($this->legalRepresentatives->contains($legalRepresentative)) {
			$this->legalRepresentatives->removeElement($legalRepresentative);
			// set the owning side to null (unless already changed)
			if ($legalRepresentative->getCompany() === $this) {
				$legalRepresentative->setCompany(null);
			}
		}

		return $this;
	}

	public function getSales(): ?int
	{
		return $this->sales;
	}

	public function setSales($sales): self
	{
		$this->sales = $this->formatInt($sales);

		return $this;
	}

	public function getTurnover(): ?string
	{
		return $this->turnover;
	}

	public function setTurnover($turnover): self
	{
        if( is_int($turnover) )
            $turnover = 'turnover'.$turnover;

		$this->turnover = $turnover;

		return $this;
	}

    public function setKind($kind): self
    {
        if (is_array($kind)) {

            $kind = array_map(function ($item) {
                return 'C' . $item;
            }, $kind);

        }

        $this->kind = $kind;
        return $this;
    }

    public function getKind(): ?array
    {
        return $this->kind;
    }

}
