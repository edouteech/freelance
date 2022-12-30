<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Exception;
use DateTimeInterface;
use App\DBAL\UserTypeEnum;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;
use JMS\Serializer\Annotation as Serializer;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Repository\UserRepository;


/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @Table(uniqueConstraints={@UniqueConstraint(name="uuid", columns={"login","company_id","contact_id"})})
 */
class User extends AbstractEntity implements UserInterface
{

    public static $legalRepresentative = 'legal_representative';
    public static $collaborator = 'collaborator';
    public static $commercialAgent = 'commercial_agent';
    public static $student = 'student';

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Exclude()
     */
    protected $login;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var DateTime
     */
    private $passwordRequestedAt;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $token;

    /**
     * @var string The hashed password
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Exclude()
     */
    protected $password;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Role")
     */
    protected $roles;

    /**
     * @var string
     * @Serializer\Exclude()
     */
    private $salt;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Contact")
     */
    private $contact;

    /**
     * @ORM\Column(type=UserTypeEnum::class, nullable=true)
     */
    private $type;

    /**
     * @var Company
     * @ORM\ManyToOne(targetEntity="App\Entity\Company")
     */
    private $company;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $lastLoginAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $loggedAt;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $hasConfirmed;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $changePassword=0;

    /**
     * @ORM\OneToOne(targetEntity=Registration::class, cascade={"persist", "remove"})
     */
    private $registration;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $notifiedAt;


    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $requestLogoutAt;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $dashboard;

    private $isNew=false;


    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    public function getPasswordRequestedAt()
    {
        return $this->passwordRequestedAt;
    }

    public function setPasswordRequestedAt($passwordRequestedAt)
    {
        $this->passwordRequestedAt = $passwordRequestedAt;
        return $this;
    }

    public function getToken()
    {
        return $this->token;
    }

	/**
	 * @param $token
	 * @return $this
	 * @throws Exception
	 */
	public function setToken($token)
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

    public function isPasswordRequestedInTime()
    {
        $passwordRequestedAt = $this->getPasswordRequestedAt();

        if ($passwordRequestedAt === null)
            return false;

        $now = new DateTime();
        $interval = $now->getTimestamp() - $passwordRequestedAt->getTimestamp();

        return $interval < 60 * 10;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setLogin(?string $login): self
    {
        $this->login = $login;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername()
    {
        return $this->id;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        if( $password )
	        $this->setChangePassword(false);

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     */
    public function isClient()
    {
        return $this->isStudent() || $this->isCollaborator() || $this->isMember();
    }

    /**
     * @return array
     */
    public function getRoles($inherited=true)
    {
        $roles = [];

        if( $inherited ){

        if( $this->getId() )
            $roles[] = 'ROLE_USER';

        if( $contact = $this->getContact() )
            $roles[] = 'ROLE_CONTACT';

        if( $this->isClient() ){

            $roles[] = 'ROLE_CLIENT';

            if( $contact && $contact->isMember() ){

                $roles[] = 'ROLE_MEMBER';
                $roles[] = 'ROLE_COMMERCIAL_AGENT';
            }

            if( $company = $this->getCompany() ){

                if( $this->isLegalRepresentative() ){

                    $roles[] = 'ROLE_COMPANY';

                    if( $company->isMember() )
                        $roles[] = 'ROLE_MEMBER';
                }

                if( $company->isMember() )
                    $roles[] = 'ROLE_SIGNATURE';
            }
        }
        }

        foreach ($this->roles as $role) {
            $roles[] = $role->getName();
            if (in_array('ROLE_ADMIN', $roles)) {
                array_push($roles, 'ROLE_USER', 'ROLE_CLIENT', 'ROLE_CONTACT', 'ROLE_MEMBER', 'ROLE_COMPANY', 'ROLE_SIGNATURE');
            }
        }

        return array_values(array_unique($roles));
    }

    public function hasRole($role){

        $roles = $this->getRoles();

        return in_array($role, $roles);
    }

    public function hasCustomRoles(){

        return count($this->roles) > 0 && !$this->isMember();
    }

    /**
     * @return array
     */
    public function getInaccessibleRoles()
    {
        $roles = ['ROLE_USER', 'ROLE_CLIENT', 'ROLE_CONTACT', 'ROLE_MEMBER', 'ROLE_COMPANY', 'ROLE_SIGNATURE'];

        return array_diff($roles, $this->getRoles());
    }

    public function addRole(Role $role): self
    {
        if (!$this->roles->contains($role)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function setRoles($roles): self
    {
    	foreach ($this->roles as $role)
            $this->roles->removeElement($role);

        if( $roles )
            $this->addRoles($roles);

        return $this;
    }

    public function addRoles($roles): self
    {
    	if( $roles && is_array($roles) ){

		    foreach ($roles as $role)
			    $this->addRole($role);
	    }

        return $this;
    }

    public function removeRole(Role $role): self
    {
        if ($this->roles->contains($role)) {
            $this->roles->removeElement($role);
        }

        return $this;
    }

    public function getContact(): ?Contact
    {
        if( $this->contact )
            return $this->contact;

        //todo: remove
        if( $this->getType() == 'company' && $company = $this->getCompany() )
            return $company->getLegalRepresentative();

        return null;
    }

    public function setContact(?Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getEmail()
    {
        if( !$this->type && $this->hasConfirmed() ){

            return $this->getLogin();
        }
        elseif( ($this->isCommercialAgent() || $this->isStudent()) && $contact = $this->getContact() ){

            if( $address = $contact->getHomeAddress() )
                return $address->getEmail();
        }
        elseif( $this->isCollaborator() && $contact = $this->getContact() ){

            if( $address = $contact->getWorkAddress($this->getCompany()) )
                return $address->getEmail();
        }
        elseif( $this->isLegalRepresentative() && $company = $this->getCompany() ){

            if( $contact = ($this->getContact()?:$company->getLegalRepresentative()) ){

                if( $workAddress = $contact->getWorkAddress($company) ){

                    if( $email = $workAddress->getEmail() )
                        return $email;
                }

                if( $homeAddress = $contact->getHomeAddress() ){

                    if( $email = $homeAddress->getEmail() )
                        return $email;
                }
            }

            return $company->getEmail();
        }

        return null;
    }

    /**
     * @return Address|null
     */
    public function getAddressWithEmail($company=null)
    {
        if( !$this->type && $this->hasConfirmed() ){

            return null;
        }
        elseif( ($this->isCommercialAgent() ||$this->isStudent()) && $contact = $this->getContact() ){

            if( $address = $contact->getHomeAddress() )
                return $address;
        }
        elseif( $this->isCollaborator() && $contact = $this->getContact() ){

            $company = $company?:$this->getCompany();

            if( $address = $contact->getWorkAddress($company) )
                return $address;
        }
        elseif( $this->isLegalRepresentative() && $company = $company?:$this->getCompany() ){

            if( $contact = ($this->getContact()?:$company->getLegalRepresentative()) ){

                if( $workAddress = $contact->getWorkAddress($company) ){

                    if( $workAddress->getEmail() )
                        return $workAddress;
                }

                if( $homeAddress = $contact->getHomeAddress() ){

                    if( $homeAddress->getEmail() )
                        return $homeAddress;
                }
            }
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function getMemberId()
    {
        if( !$this->type && $this->hasConfirmed() )
            return false;
        elseif( $this->isCommercialAgent() && $contact = $this->getContact() )
            return $contact->getMemberId();
        elseif( $company = $this->getCompany() )
            return $company->getMemberId();

        return false;
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        if( !$this->type && $this->hasConfirmed() )
            return $this->getLogin();
        elseif( $contact = $this->getContact() )
            return $contact->getFirstname().' '.$contact->getLastname();
        elseif( $company = $this->getCompany() )
            return $company->getName();

        return null;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

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

    public function getLastLoginAt(): ?DateTimeInterface
    {
        if( $this->lastLoginAt )
            return $this->lastLoginAt;
        elseif( $this->loggedAt )
            return $this->loggedAt;

        return new DateTime();
    }

    public function setLastLoginAt(?DateTimeInterface $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getLoggedAt(): ?DateTimeInterface
    {
        return $this->loggedAt;
    }

    public function setLoggedAt(?DateTimeInterface $loggedAt): self
    {
        $this->loggedAt = $loggedAt;

        return $this;
    }

    public function getHasConfirmed(): ?bool
    {
        return $this->hasConfirmed;
    }

    public function hasConfirmed(): ?bool
    {
        return $this->getHasConfirmed();
    }

    public function setHasConfirmed(?bool $hasConfirmed): self
    {
        $this->hasConfirmed = $this->formatBool($hasConfirmed);

        return $this;
    }

    public function getChangePassword(): ?bool
    {
        return $this->changePassword;
    }

    public function setChangePassword(?bool $changePassword): self
    {
        $this->changePassword = $this->formatBool($changePassword);

        return $this;
    }

    public function getRegistration(): ?Registration
    {
        return $this->registration;
    }

    public function setRegistration(?Registration $registration): self
    {
        $this->registration = $registration;

        return $this;
    }

    public function getNotifiedAt(): ?DateTimeInterface
    {
        if( $this->notifiedAt )
            return $this->notifiedAt;
        else
            return $this->getLastLoginAt();
    }

    public function getRequestLogoutAt(): ?DateTimeInterface
    {
        if( $this->requestLogoutAt )
            return $this->requestLogoutAt;
        else
            return $this->getLastLoginAt();
    }

    /**
     * @param $notifiedAt
     * @return $this
     * @throws Exception
     */
    public function setNotifiedAt($notifiedAt): self
    {
        $this->notifiedAt = $this->formatDateTime($notifiedAt);

        return $this;
    }

    /**
     * @param $requestLogoutAt
     * @return $this
     * @throws Exception
     */

    public function setRequestLogoutAt($requestLogoutAt): self
    {
        $this->requestLogoutAt = $this->formatDateTime($requestLogoutAt);

        return $this;
    }

    public function isLegalRepresentative(): ?bool
    {
        return $this->getType() == 'company' || $this->getType() == User::$legalRepresentative;
    }

    public function isCommercialAgent(): ?bool
    {
        return $this->getType() == 'contact' || $this->getType() == User::$commercialAgent;
    }

    public function isCollaborator(): ?bool
    {
        return $this->getType() == User::$collaborator;
    }

    public function isStudent(): ?bool
    {
        return $this->getType() == User::$student;
    }

    public function isMember(): ?bool
    {
        $contact = $this->getContact();
        $company = $this->getCompany();

        return ($this->isLegalRepresentative() && $company && $company->isMember()) || ($this->isCommercialAgent() && $contact && $contact->isMember());
    }

    public function getDashboard(): ?array
    {
    	if( is_string($this->dashboard) ){

		    $dashboard = json_decode($this->dashboard, true);

		    $dashboard['hasNotification'] = boolval($dashboard['hasNotification']??false);
		    $dashboard['isAccessible'] = boolval($dashboard['isAccessible']??false);

		    return $dashboard;
	    }

		return null;
    }

    public function setDashboard(?string $dashboard): self
    {
        $this->dashboard = $dashboard;

        return $this;
    }

    public function setHasNotification($hasNotification): self
    {
        $dashboard = $this->getDashboard();
        $dashboard['hasNotification'] =  $this->formatBool($hasNotification);

        $this->setDashboard(json_encode($dashboard));

        return $this;
    }

    public function setIsAccessible($isAccessible): self
    {
        $dashboard = $this->getDashboard();
        $dashboard['isAccessible'] =  $this->formatBool($isAccessible);

        $this->setDashboard(json_encode($dashboard));

        return $this;
    }

    public function hasNotification(): bool
    {
        $dashboard = $this->getDashboard();
        return $dashboard['hasNotification']??false;
    }

    public function isAccessible(): bool
    {
        $dashboard = $this->getDashboard();
	    return $dashboard['isAccessible']??false;
    }

	public function getLastSyncAt(): ?DateTimeInterface
   	{
   		$dashboard = $this->getDashboard();
   
   		if( is_string($dashboard['lastSyncAt']??null) ){
   
   			return new DateTime($dashboard['lastSyncAt']);
   		}
   		else{
   
   			$lastSyncAt = new DateTime();
   			$lastSyncAt->setTimestamp($dashboard['lastSyncAt']??null);
   
   			return $lastSyncAt;
   		}
   	}

	public function getLastFullSyncAt(): ?DateTimeInterface
   	{
   		$dashboard = $this->getDashboard();
   
   		if( is_string($dashboard['lastFullSyncAt']??null) ){
   
   			return new DateTime($dashboard['lastFullSyncAt']);
   		}
   		else{
   
   			$lastFullSyncAt = new DateTime();
               $lastFullSyncAt->setTimestamp($dashboard['lastFullSyncAt']??null);
   
   			return $lastFullSyncAt;
   		}
   	}

	/**
	 * @param $lastSyncAt
	 * @return $this
	 * @throws Exception
	 */
	public function setLastSyncAt($lastSyncAt): self
   	{
   		$dashboard = $this->getDashboard();
   		$dashboard['lastSyncAt'] = $this->formatDateTime($lastSyncAt)->getTimestamp();
   
   		$this->setDashboard(json_encode($dashboard));
   
   		return $this;
   	}

	/**
	 * @param $lastSyncAt
	 * @return $this
	 * @throws Exception
	 */
	public function setLastFullSyncAt($lastSyncAt): self
   	{
   		$dashboard = $this->getDashboard();
   		$dashboard['lastFullSyncAt'] = $this->formatDateTime($lastSyncAt)->getTimestamp();
   
   		$this->setDashboard(json_encode($dashboard));
   
   		return $this;
   	}

	public function isRegistering(){
   
   		if( $registration = $this->getRegistration() )
   			return ($this->isCommercialAgent() && !$registration->getValidCaci()) || ($this->isStudent() && !$registration->getAgencies()) || !$registration->getInformation();
   
   		return false;
   	}

	public function startedRegistration(){
   
   		if( $registration = $this->getRegistration() )
   			return !$registration->getInformation();
   
   		return false;
   	}

    /**
     * @return bool
     */
    public function isNew(): bool
    {
        return $this->isNew;
    }

    /**
     * @param bool $isNew
     */
    public function setIsNew(bool $isNew): void
    {
        $this->isNew = $isNew;
    }
}
