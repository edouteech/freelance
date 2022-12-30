<?php /** @noinspection PhpPrivateFieldCanBeLocalVariableInspection */

namespace App\Entity;

use Combodo\DoctrineEncryptBundle\Configuration\Encrypted;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Serializer\Annotation\Groups;
use App\Repository\AddressRepository;

/**
 * @ORM\Entity(repositoryClass=AddressRepository::class)
 */
class Address extends AbstractEudoEntity
{
    private $index=0;

    public $position;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Contact", inversedBy="addresses")
	 * @ORM\JoinColumn(nullable=false)
	 * @Groups({"eudonet","insert"})
	 */
	private $contact;

	/**
	 * @ORM\ManyToOne(targetEntity="App\Entity\Company")
	 * @ORM\JoinColumn(nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $company;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert","update"})
	 * @Encrypted
	 */
	private $email;
	private $emailDecrypted;

	/**
	 * @ORM\Column(type="string", length=40, nullable=true)
	 */
	private $emailHash;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $summary;

	/**
	 * @ORM\Column(type="string", length=15, nullable=true)
	 * @Groups({"eudonet","insert","update"})
	 */
	private $phone;


	private $street;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert","update"})
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
	 * @ORM\Column(type="string", length=10, nullable=true)
	 * @Groups({"eudonet","insert","update"})
	 */
	private $zip;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert","update"})
	 */
	private $city;

	/**
	 * @ORM\Column(type="string", length=50, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $country='France';

	/**
	 * @ORM\Column(type="float", nullable=true)
	 */
	private $lat;

	/**
	 * @ORM\Column(type="float", nullable=true)
	 */
	private $lng;

	/**
	 * @ORM\Column(type="boolean")
	 * @Groups({"eudonet","insert","update"})
	 */
	private $isActive=true;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $isExpert=false;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $isRealEstateAgent=false;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $isOtherCollaborator=false;

	/**
	 * @ORM\Column(type="boolean")
	 * @Groups({"eudonet","insert"})
	 */
	private $isMain=false;

	/**
	 * @ORM\Column(type="boolean")
	 */
	private $isArchived=0;

	/**
	 * @ORM\Column(type="string", length=255, nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $positions;

	/**
	 * @ORM\Column(type="date", nullable=true)
	 * @Groups({"eudonet","insert","update"})
	 */
	private $startedAt;

	/**
	 * @ORM\Column(type="date", nullable=true)
	 */
	private $endedAt;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 * @Groups({"eudonet","insert"})
	 */
	private $isHome;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 */
	private $isCommercialAgent;

	/**
	 * @ORM\Column(type="date", nullable=true)
	 * @Groups({"eudonet","insert","update"})
	 */
	private $issuedAt;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 * @Groups({"eudonet","insert","update"})
	 */
	private $hasCertificate;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	private $expireAt;

	public function getIndex(): int
	{
		return $this->index;
	}

	public function setIndex(int $index): self
	{
		$this->index = $index;

		return $this;
	}

	public function prePersist()
	{
		parent::prePersist();

		if( $endedAt = $this->getEndedAt() )
			$this->setIsActive($endedAt > new DateTime());

		return $this;
	}

	public function getEmail(): ?string
	{
		if (!empty($this->emailDecrypted))
			return $this->emailDecrypted;

		return $this->email;
	}

	public function getRawEmail(): ?string
	{
		return $this->email;
	}

	public function setEmail(?string $email): self
	{
		$this->email = $email;
		$this->emailDecrypted = null;

        return $this;
	}

	public function getEmailHash(): ?string
	{
		return $this->emailHash;
	}

	public function setEmailHash($email): self
	{
		$this->emailHash = $email ? sha1($email) : null;

		return $this;
	}

	public function getSummary(): ?string
	{
		return $this->summary;
	}

	public function setSummary(?string $summary): self
	{
		$this->summary = $summary;

		return $this;
	}

	public function setEmailDecrypted(?string $emailDecrypted) : self
	{
		$this->emailDecrypted = $emailDecrypted;
        $this->setEmailHash($emailDecrypted);

        return $this;
	}

	public function getEmailDecrypted() : ?string
	{
		return $this->emailDecrypted;
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

	public function getStreet1(): ?string
	{
		return $this->street1;
	}

	public function setStreet1(?string $street1): self
	{
		$this->street1 = $this->formatStreet($street1);

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

	public function isExpert(): ?bool
	{
		return $this->isExpert;
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

	public function getContact(): ?Contact
	{
		return $this->contact;
	}

	public function setContact(?Contact $contact): self
	{
		$this->contact = $contact;

		return $this;
	}

	public function setContactId(?int $contact_id): self
	{
		if( $contact_id ){

			$contact = new Contact();
			$contact->setId($contact_id);

			$this->setContact($contact);
		}

		return $this;
	}

	public function getCompany(): ?Company
	{
		return $this->company;
	}

	public function setCompany(?Company $company, $copyAddress=false): self
	{
		if( $this->isHome() )
			return $this;

		$this->company = $company;

		if( $company && $copyAddress && empty($this->getCity()) ){

			$this->setZip($company->getZip());
			$this->setCity($company->getCity());
			$this->setStreet1($company->getStreet1());
			$this->setStreet2($company->getStreet2());
			$this->setStreet3($company->getStreet3());
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

			$this->street = explode($glue, $street);

			if( count($this->street) >= 1)
				$this->setStreet1($this->street[0]);

			if( count($this->street) >= 2)
				$this->setStreet2($this->street[1]);

			if( count($this->street) >= 3)
				$this->setStreet3($this->street[2]);
		}

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

	public function setCountry(?string $country): self
	{
		$this->country = $country;

		return $this;
	}

	public function isActive(): ?bool
	{
		return $this->getIsActive() && !$this->getIsArchived();
	}

	public function getIsActive(): ?bool
	{
		return $this->isActive;
	}

	public function setIsActive($isActive): self
	{
		$this->isActive = $this->formatBool($isActive);
		$this->setIsArchived($this->isActive);

		return $this;
	}

	public function isMain(): ?bool
	{
		return $this->getIsMain();
	}

	public function getIsMain(): ?bool
	{
		return $this->isMain;
	}

	public function setIsMain($isMain): self
	{
		$this->isMain = filter_var($isMain, FILTER_VALIDATE_BOOLEAN);

		return $this;
	}

	public function isArchived(): ?bool
	{
		return $this->getIsArchived();
	}

	public function getIsArchived(): ?bool
	{
		return $this->isArchived;
	}

	public function setIsArchived($isArchived): self
	{
		$this->isArchived = $this->formatBool($isArchived);

		return $this;
	}

	public function getLatLng(): ?array
	{
		return [$this->lat, $this->lng];
	}

	public function setLatLng(?string $lat_lng): self
	{
		list($this->lat, $this->lng) = $this->formatPoint($lat_lng);

		return $this;
	}

	public function getLat(): ?string
	{
		return $this->lat;
	}

	public function setLat(?float $lat): self
	{
		$this->lat = $lat;

		return $this;
	}

	public function getLng(): ?float
	{
		return $this->lng;
	}

	public function setLng(?float $lng): self
	{
		$this->lng = $lng;

		return $this;
	}

	/**
	 * @param $format
	 * @return array|string
	 */
	public function getPositions($format=false)
    {
        if( $format ){

            return [
                'isExpert'=>$this->getIsExpert(),
                'isCommercialAgent'=>$this->getIsCommercialAgent(),
                'isRealEstateAgent'=>$this->getIsRealEstateAgent(),
                'isOtherCollaborator'=>$this->getIsOtherCollaborator(),
            ];
        }

        return $this->positions;
    }

	public function matchPosition($position): ?bool
	{
		if( $this->positions )
			return strpos($this->positions, $position) > -1;

		return false;
	}

	public function setPositions(?string $positions): self
	{
		$positions = explode(';', $positions);

		foreach ($positions as $position )
			$this->setPosition($position);

		return $this;
	}

	public function setPosition(?string $position): self
	{
		$positions = explode(';', $this->getPositions());
		$position = trim($position);

		if(!in_array($position, $positions) )
			$positions[] = $position;

		if( $position == 'Expert immobilier' )
			$this->setIsExpert(true);

		if( $position == 'Agent commercial' )
			$this->setIsCommercialAgent(true);

		if( $position == 'Négociateur immobilier' )
			$this->setIsRealEstateAgent(true);

		if( $position == 'Autre collaborateur' )
			$this->setIsOtherCollaborator(true);

		$this->positions = implode(';', array_filter($positions));

		return $this;
	}

	public function getStartedAt(): ?DateTimeInterface
	{
		return $this->startedAt;
	}

	/**
	 * @param $startedAt
	 * @return $this
	 * @throws Exception
	 */
	public function setStartedAt($startedAt): self
	{
		$this->startedAt = $this->formatDateTime($startedAt);

		return $this;
	}

	public function getEndedAt(): ?DateTimeInterface
	{
		return $this->endedAt;
	}

	/**
	 * @param $endedAt
	 * @return $this
	 * @throws Exception
	 */
	public function setEndedAt($endedAt): self
	{
		$this->endedAt = $this->formatDateTime($endedAt);

		return $this;
	}

	public function getIsHome(): ?bool
	{
		return $this->isHome;
	}

	public function isHome(): ?bool
	{
		return $this->getIsHome();
	}

	public function setIsHome($isHome): self
	{
		$this->isHome = $this->formatBool($isHome);

		if( $isHome )
			$this->company = null;

		return $this;
	}

	public function getIsCommercialAgent(): ?bool
	{
		return $this->isCommercialAgent;
	}

	public function isCommercialAgent(): ?bool
	{
		return $this->getIsCommercialAgent();
	}

	public function setIsCommercialAgent($isCommercialAgent): self
	{
		$this->isCommercialAgent = $this->formatBool($isCommercialAgent);

		return $this;
	}

	public function getIsRealEstateAgent(): ?bool
	{
		return $this->isRealEstateAgent;
	}

	public function isRealEstateAgent(): ?bool
	{
		return $this->getIsRealEstateAgent();
	}

	public function setIsRealEstateAgent($isRealEstateAgent): self
	{
		$this->isRealEstateAgent = $this->formatBool($isRealEstateAgent);

		return $this;
	}

    public function getIsOtherCollaborator(): ?bool
    {
        return $this->isOtherCollaborator;
    }

    public function isOtherCollaborator(): ?bool
    {
        return $this->getIsOtherCollaborator();
    }

	public function setIsOtherCollaborator($isOtherCollaborator): self
	{
		$this->isOtherCollaborator = $this->formatBool($isOtherCollaborator);

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

	public function getHasCertificate(): ?bool
	{
		return $this->hasCertificate;
	}

	public function hasCertificate(): ?bool
	{
		return $this->getHasCertificate();
	}

	public function setHasCertificate($hasCertificate): self
	{
		$this->hasCertificate = $this->formatBool($hasCertificate);

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
}
