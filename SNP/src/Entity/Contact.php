<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Combodo\DoctrineEncryptBundle\Configuration\Encrypted;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ContactRepository")
 */
class Contact extends AbstractEudoEntity
{
    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $memberId;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert","update"})
     */
    private $firstname;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert","update"})
     */
    private $lastname;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert","update"})
     */
    private $birthname;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Address", mappedBy="contact")
     */
    private $addresses;

    /**
     * @ORM\Column(type="string", length=10, nullable=true)
     * @Groups({"eudonet","insert","update"})
     */
    private $civility;

    /**
     * @ORM\Column(type="date", nullable=true)
     * @Groups({"eudonet","insert","update"})
     */
    private $birthday;

    /**
     * @Groups({"eudonet","insert"})
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $legalForm;

    /**
     * @ORM\OneToMany(targetEntity="FormationParticipant", mappedBy="contact")
     */
    private $formationCourses;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"eudonet","update","insert"})
     */
    private $hasDashboard=0;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert","update"})
     * @Encrypted
     */
    private $eLearningEmail;
    private $eLearningEmailDecrypted;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert","update"})
     * @Encrypted
     */
    private $eLearningPassword;
    private $eLearningPasswordDecrypted;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert","update"})
     */
    private $eLearningToken;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $eLearningV2;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $avatar;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $rsac;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","insert"})
     */
    private $birthPlace;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\CompanyRepresentative", mappedBy="contact")
     */
    private $legalRepresentatives;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $token;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $passwordRequestedAt;

    /**
     * @ORM\OneToMany(targetEntity=FormationInterest::class, mappedBy="contact", orphanRemoval=true, fetch="EXTRA_LAZY")
     */
    private $formationsInterest;

	/**
	 * @ORM\OneToMany(targetEntity="App\Entity\ContactMetadata", mappedBy="contact", orphanRemoval=true)
	 */
	protected $metadata;

    /**
     * @Groups({"eudonet","insert"})
     */
    private $politePhrase;

    public function __construct($id=false)
    {
        parent::__construct($id);

	    $this->metadata = new ArrayCollection();
	    $this->addresses = new ArrayCollection();
        $this->formationCourses = new ArrayCollection();
        $this->legalRepresentatives = new ArrayCollection();
        $this->formationsInterest = new ArrayCollection();
    }

	/**
	 * @return Collection|ContactMetadata[]
	 */
	public function getMetadata(): Collection
      	{
      		return $this->metadata;
      	}

	public function addMetadata(ContactMetadata $metadata): self
      	{
      		if (!$this->metadata->contains($metadata)) {
      			$this->metadata[] = $metadata;
      			$metadata->setContact($this);
      		}
      
      		return $this;
      	}

	public function removeMetadata(ContactMetadata $metadata): self
      	{
      		if ($this->metadata->contains($metadata)) {
      			$this->metadata->removeElement($metadata);
      			// set the owning side to null (unless already changed)
      			if ($metadata->getContact() === $this) {
      				$metadata->setContact(null);
      			}
      		}
      
      		return $this;
      	}

	public function getPolitePhrase(): ?string
          {
              return $this->politePhrase;
          }

    public function setPolitePhrase(?string $phrase): self
    {
        $this->politePhrase = $phrase;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function getEmail(?Company $company=null): ?string
    {
        if( $this->isMember() && $company )
            $company = null;

        if( $address = $this->getAddress($company) )
            return $address->getEmail();

        return null;
    }

    public function getPhone(?Company $company=null): ?string
    {
        if( $this->isMember() && $company )
            $company = null;

        if( $address = $this->getAddress($company) )
            return $address->getPhone();

        return null;
    }

    public function getAddress(?Company $company=null, $active=true){
        if( $company )
            return $this->getWorkAddress($company, $active);
        else
            return $this->getHomeAddress($active);
    }

    public function getAddressById($id): ?Address
    {
        foreach($this->getAddresses() as $address){

            if( $address->getId() == $id )
                return $address;
        }

        return null;
    }

    public function getHomeAddress($active=true): ?Address
    {
        //todo: simplify
        $addresses = [];

        foreach($this->getAddresses() as $address){

            if( $address->isHome() && (!$active || $address->isActive() ) )
                $addresses[] = $address;
        }

        if( count($addresses) == 0 )
            return null;

        foreach ($addresses as $address){

            if( $address->isMain() )
                return $address;
        }

        foreach ($addresses as $address){

            if( $address->isActive() )
                return $address;
        }

        return $addresses[0];
    }

    public function getWorkAddress(?Company $company=null, $active=true): ?Address
    {
        //todo: simplify
        $addresses = [];

        foreach($this->getAddresses() as $address){

            if( (!$company || $address->getCompany() === $company) && (!$active || $address->isActive()) && !$address->isHome() )
                $addresses[] = $address;
        }

        if( !count($addresses) )
            return null;

        foreach ($addresses as $address){

            if( $address->isMain() )
                return $address;
        }

        foreach ($addresses as $address){

            if( $address->isActive() )
                return $address;
        }

        if( count($addresses) )
            return $addresses[0];

        return null;
    }

    public function getMainAddress(): ?Address
    {
        foreach($this->getAddresses() as $address){

            if( $address->isMain() && $address->isActive() )
                return $address;
        }

        return null;
    }

    /**
     * @param bool $active
     * @return Address[]
     */
    public function getWorkAddresses($active=true)
    {
        //todo: simplify
        $addresses = [];

        foreach($this->getAddresses() as $address){

            if( $address->getCompany() && (!$active || ($address->isActive() && !$address->isArchived()) ) )
                $addresses[] = $address;
        }

        return $addresses;
    }

    /**
     * @param $company
     * @return int
     */
    public function getSeniority(?Company $company){

        if( $company && $businessCard = $company->getBusinessCard() ){

            $expiredAt = $businessCard->getExpireAt();
            $address = $this->getWorkAddress($company);

            if( $expiredAt && $address ){

                if( $this->isLegalRepresentative($company) ){

                    if( $startedAt = $address->getStartedAt() ){

                        return ( $expiredAt->getTimestamp() - $startedAt->getTimestamp() ) / (60*60*24);
                    }
                }
                elseif( $issuedAt = $address->getIssuedAt() ){

                    return ( $expiredAt->getTimestamp() - $issuedAt->getTimestamp() ) / (60*60*24);
                }
            }
        }

        return false;
    }

    /**
     * @param $seniority
     * @return false|float
     */
    public function getFormationsQuota($seniority){

        $quotaFormationsPerYear = intval($_ENV['FORMATION_QUOTA_PER_YEAR']??14);
        $formationsQuotaMax = intval($_ENV['FORMATION_QUOTA_MAX']??42);

        return ceil(max(0, min($formationsQuotaMax, $quotaFormationsPerYear/365 * $seniority)));
    }

    public function hasElearningAccount(): bool
    {
        return (bool)$this->getElearningToken();
    }


    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getMemberId(): ?string
    {
        return $this->memberId;
    }

    public function isMember(): bool
    {
        return $this->memberId && $this->getStatus() == 'member';
    }

    public function setMemberId(?string $memberId): self
    {
        $this->memberId = $memberId;

        return $this;
    }

	/**
	 * @return array
	 */
	public function getFunctions()
      	{
      		$functions = [];
      
      		foreach ($this->getAddresses() as $address) {
      
      			if ( $address->isActive() && $address->isExpert() )
      				$functions[] = 'expert';
      		}
      
      		return array_unique($functions);
      	}

    /**
     * @return Collection|Address[]
     */
    public function getAddresses(): Collection
    {
        if( !$this->addresses )
            return new ArrayCollection();

        foreach ($this->addresses as $index=>&$address)
            $address->setIndex($index);

        return $this->addresses;
    }

    public function addAddress(Address $address): self
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses[] = $address;
            $address->setContact($this);
        }

        return $this;
    }

    public function removeAddress(Address $address): self
    {
        if ($this->addresses->contains($address)) {
            $this->addresses->removeElement($address);
            // set the owning side to null (unless already changed)
            if ($address->getContact() === $this) {
                $address->setContact(null);
            }
        }

        return $this;
    }

    public function removeAddresses(): self
    {
        foreach ($this->addresses as $address ){
            $this->addresses->removeElement($address);
        }

        return $this;
    }

    public function addAddresses($addresses): self
    {
        foreach ($addresses as $address ){
            $this->addAddress($address);
        }

        return $this;
    }

    /**
     * @return Collection|FormationParticipant[]
     */
    public function getFormationCourses(): Collection
    {
        return $this->formationCourses;
    }

    public function addFormationCourse(FormationParticipant $formationParticipant): self
    {
        if (!$this->formationCourses->contains($formationParticipant)) {
            $this->formationCourses[] = $formationParticipant;
            $formationParticipant->setContact($this);
        }

        return $this;
    }

    public function removeFormationCourse(FormationParticipant $formationParticipant): self
    {
        if ($this->formationCourses->contains($formationParticipant)) {
            $this->formationCourses->removeElement($formationParticipant);
            // set the owning side to null (unless already changed)
            if ($formationParticipant->getContact() === $this) {
                $formationParticipant->setContact(null);
            }
        }

        return $this;
    }

    public function getCivility(): ?string
    {
        return $this->civility;
    }

    public function setCivility(?string $civility): self
    {
        $this->civility = $civility;

        return $this;
    }


    public function getBirthday(): ?DateTimeInterface
    {
        return $this->birthday;
    }

    /**
     * @param string|null $birthday
     * @return $this
     * @throws Exception
     */
    public function setBirthday($birthday): self
    {
        $this->birthday = $this->formatDateTime($birthday);

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        switch ($status){

            case 'Radié':
            case 'removed': $status = 'removed'; break;

            case 'Adhérent':
            case 'member': $status = 'member'; break;

            case 'Non adhérent':
            case 'not_member': $status = 'not_member'; break;

            case 'Adhésion refusée':
            case 'refused': $status = 'refused'; break;

            case 'Etudiant':
            case 'student': $status = 'student'; break;

            default: $status = NULL; break;
        }

        $this->status = $status;

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

    public function getLegalForm(): ?string
    {
        return $this->legalForm;
    }

    public function setLegalForm(?string $legalForm): self
    {
        $this->legalForm = $legalForm;

        return $this;
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

    public function getElearningEmail(): ?string
    {
        if( !empty($this->eLearningEmailDecrypted) )
            return $this->eLearningEmailDecrypted;

        return $this->eLearningEmail;
    }

	public function setElearningEmail(?string $eLearningEmail): self
      	{
      		$this->eLearningEmail = $eLearningEmail;
      		$this->eLearningEmailDecrypted = null;
      
              return $this;
          }

    public function getElearningEmailDecrypted(): ?string
    {
        return $this->eLearningEmailDecrypted;
    }

    public function setElearningEmailDecrypted(?string $eLearningEmailDecrypted): self
    {
        $this->eLearningEmailDecrypted = $eLearningEmailDecrypted;

        return $this;
    }

    public function getElearningPassword(): ?string
    {
        if( !empty($this->eLearningPasswordDecrypted) )
            return $this->eLearningPasswordDecrypted;

        return $this->eLearningPassword;
    }

	public function setElearningPassword(?string $eLearningPassword): self
      	{
      		$this->eLearningPassword = $eLearningPassword;
      		$this->eLearningPasswordDecrypted = null;
      
              return $this;
          }

    public function getElearningPasswordDecrypted(): ?string
    {
        return $this->eLearningPasswordDecrypted;
    }

    public function setElearningPasswordDecrypted(?string $eLearningPasswordDecrypted): self
    {
        $this->eLearningPasswordDecrypted = $eLearningPasswordDecrypted;

        return $this;
    }

    public function getElearningToken(): ?string
    {
        return $this->eLearningToken;
    }

    public function setElearningToken(?string $eLearningToken): self
    {
        $this->eLearningToken = $eLearningToken;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname($lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getBirthname(): ?string
    {
        return $this->birthname;
    }

    public function setBirthname($birthname): self
    {
        $this->birthname = $birthname;

        return $this;
    }

    public function getFullname(): ?string
    {
        if ($this->firstname && $this->lastname) {
            return sprintf('%s %s', $this->firstname, $this->lastname);
        }

        return null;
    }

    public function getAvatar()
    {
        return $this->avatar;
    }

    public function setAvatar($avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getRsac(): ?string
    {
        return $this->rsac;
    }

    public function setRsac(?string $rsac): self
    {
        $this->rsac = $rsac;

        return $this;
    }

    public function getBirthPlace(): ?string
    {
        return $this->birthPlace;
    }

    public function setBirthPlace(?string $birthPlace): self
    {
        $this->birthPlace = $birthPlace;

        return $this;
    }

    /**
     * @return Collection|CompanyRepresentative[]
     */
    public function getLegalRepresentatives(): Collection
    {
        return $this->legalRepresentatives;
    }

    /**
     * @return bool
     */
    public function isStudent(): bool
    {
        return $this->getStatus() == User::$student;
    }

    /**
     * @param Company|null $company
     * @return bool
     */
    public function isLegalRepresentative(?Company $company): bool
    {
        if(!$company)
            return false;

        $legalRepresentatives = $this->getLegalRepresentatives();

        foreach ($legalRepresentatives as $legalRepresentative ){

            if( $legalRepresentative->getCompany()->getId() == $company->getId() && !$legalRepresentative->isArchived() )
                return true;
        }

        return false;
    }

    public function addLegalRepresentative(CompanyRepresentative $legalRepresentative): self
    {
        if (!$this->legalRepresentatives->contains($legalRepresentative)) {
            $this->legalRepresentatives[] = $legalRepresentative;
            $legalRepresentative->setContact($this);
        }

        return $this;
    }

    public function removeLegalRepresentative(CompanyRepresentative $legalRepresentative): self
    {
        if ($this->legalRepresentatives->contains($legalRepresentative)) {
            $this->legalRepresentatives->removeElement($legalRepresentative);
            // set the owning side to null (unless already changed)
            if ($legalRepresentative->getContact() === $this) {
                $legalRepresentative->setContact(null);
            }
        }

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

	/**
	 * @param $token
	 * @return $this
	 * @throws Exception
	 */
	public function setToken($token): self
          {
              if( $token ){
      
                  $token = $this->generateToken();
                  $this->setPasswordRequestedAt(new Datetime());
              }
              else{
      
                  $this->setPasswordRequestedAt(null);
              }
      
              $this->token = $token;
      
              return $this;
          }

    public function getPasswordRequestedAt(): ?DateTimeInterface
    {
        return $this->passwordRequestedAt;
    }

    public function setPasswordRequestedAt(?DateTimeInterface $passwordRequestedAt): self
    {
        $this->passwordRequestedAt = $passwordRequestedAt;

        return $this;
    }

    public function isPasswordRequestedInTime()
    {
        $passwordRequestedAt = $this->getPasswordRequestedAt();

        if ($passwordRequestedAt === null)
            return false;

        $now = new DateTime();
        $interval = $now->getTimestamp() - $passwordRequestedAt->getTimestamp();

        return $interval < 60 * 10;
    }

    /**
     * @return Collection|FormationInterest[]
     */
    public function getFormationsInterest(): Collection
    {
        return $this->formationsInterest;
    }

    public function addFormationInterest(FormationInterest $formationInterest): self
    {
        if (!$this->formationsInterest->contains($formationInterest)) {
            $this->formationsInterest[] = $formationInterest;
            $formationInterest->setContact($this);
        }

        return $this;
    }

    public function removeFormationInterest(FormationInterest $formationInterest): self
    {
        if ($this->formationsInterest->contains($formationInterest)) {
            $this->formationsInterest->removeElement($formationInterest);
            // set the owning side to null (unless already changed)
            if ($formationInterest->getContact() === $this) {
                $formationInterest->setContact(null);
            }
        }

        return $this;
    }

    public function __toString()
    {
        return sprintf('%s %s %s', $this->civility, $this->firstname, $this->lastname);
    }

    public function getELearningV2(): ?bool
    {
        return $this->eLearningV2;
    }

    public function setELearningV2($eLearningV2): self
    {
        $this->eLearningV2 = $this->formatBool($eLearningV2);

        return $this;
    }

    public function addFormationsInterest(FormationInterest $formationsInterest): self
    {
        if (!$this->formationsInterest->contains($formationsInterest)) {
            $this->formationsInterest[] = $formationsInterest;
            $formationsInterest->setContact($this);
        }

        return $this;
    }

    public function removeFormationsInterest(FormationInterest $formationsInterest): self
    {
        if ($this->formationsInterest->removeElement($formationsInterest)) {
            // set the owning side to null (unless already changed)
            if ($formationsInterest->getContact() === $this) {
                $formationsInterest->setContact(null);
            }
        }

        return $this;
    }
}
