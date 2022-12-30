<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Serializer\Annotation\Groups;
use App\DBAL\FormationTypeEnum;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FormationCourseRepository")
 */
class FormationCourse extends AbstractEudoEntity
{
    /**
     * @ORM\Column(type="string", columnDefinition="ENUM('completed','canceled','potential','confirmed','delayed','suspended')", nullable=true)
     * @Groups({"eudonet","update","create"})
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"eudonet","update","create"})
     */
    private $schedule;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"eudonet","update","create"})
     */
    private $days;

    /**
     * @ORM\Column(type="date")
     * @Groups({"eudonet","update","create"})
     */
    private $startAt;

    /**
     * @ORM\Column(type="date", nullable=true)
     * @Groups({"eudonet","update","create"})
     */
    private $endAt;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"eudonet","update","create"})
     */
    private $seatingCapacity;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $registrantsCount;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $remainingPlaces;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Company")
     */
    private $company;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Formation", inversedBy="courses")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"eudonet","update","create"})
     */
    private $formation;

    /**
     * @ORM\OneToMany(targetEntity="FormationParticipant", mappedBy="formationCourse", orphanRemoval=true)
     */
    private $participants;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Contact")
     * @Groups({"eudonet","update","create"})
     */
    private $instructor1;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Contact")
     * @Groups({"eudonet","update","create"})
     */
    private $instructor2;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Contact")
     * @Groups({"eudonet","update","create"})
     */
    private $instructor3;

    /**
     * @ORM\Column(type="float")
     */
    private $taxRate;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $webinarId;

    /**
     * @ORM\Column(type=FormationTypeEnum::class, nullable=true)
     * @Groups({"eudonet","create"})
     *
     */
    private $format;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $city;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $resendMail;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $hasEdit;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $editNote;

	/**
	 * @ORM\Column(type="boolean", nullable=true)
	 * @Groups({"eudonet","update"})
	 */
	private $reminded;

	public function __construct()
    {
        parent::__construct();

        $this->participants = new ArrayCollection();
    }

	public function getReminded(): ?bool
	{
		return $this->reminded;
	}

	public function setReminded($reminded): self
	{
		$this->reminded = $this->formatBool($reminded);

		return $this;
	}

	public function getStatus(): ?string
    {
        return $this->status;
    }


    public function getDays(): float
    {
        return max(1, $this->days);
    }

    public function setDays($days): self
    {
        $this->days = $this->formatFloat($days);

        return $this;
    }

    public function setStatus(?string $status): self
    {
        switch ($status){
            case 'Réalisée': $status = 'completed'; break;
            case 'Annulée': $status = 'canceled'; break;
            case 'Potentielle': $status = 'potential'; break;
            case 'Confirmée': $status = 'confirmed'; break;
            case 'Reportée': $status = 'delayed'; break;
            case 'Suspendue': $status = 'suspended'; break;
        }

        $this->status = $status;

        return $this;
    }

    public function getSchedule(): ?string
    {
        return $this->schedule;
    }

    public function setSchedule(?string $schedule): self
    {
        $this->schedule = $schedule;

        return $this;
    }

    public function getStartAt($addTime=false): ?DateTimeInterface
    {
    	if( !$this->startAt )
    		return null;

	    $startAt = clone $this->startAt;

        if( $startAt && $addTime ){

            $period = explode(' ', $this->getSchedule());
            $hours = explode('-', $period[0]);

            $startHours = explode('h', strtolower($hours[0]));

            if( count($startHours) == 2 )
	            $startAt->setTime(intval($startHours[0]), intval($startHours[1]));
            else
	            $startAt->setTime(intval($startHours[0]), 0);
        }

        return $startAt;
    }

    /**
     * @param $startAt
     * @return $this
     * @throws Exception
     */
    public function setStartAt($startAt): self
    {
        $this->startAt = $this->formatDateTime($startAt);

        return $this;
    }

    public function getEndAt($addTime=false): ?DateTimeInterface
    {
	    if( !$this->endAt )
		    return null;

	    $endAt = clone $this->endAt;

	    if( $endAt && $addTime ){

		    $period = explode(' ', $this->getSchedule());

		    if( count($period) > 1)
			    $hours = explode('-', $period[1]);
		    else
			    $hours = explode('-', $period[0]);

		    $endHours = explode('h', strtolower($hours[1]));

		    if( count($endHours) == 2 )
			    $endAt->setTime(intval($endHours[0]), intval($endHours[1]));
		    else
			    $endAt->setTime(intval($endHours[0]), 0);
	    }

        return $endAt;
    }

    public function getDayEndAt($addTime=false): ?DateTimeInterface
    {
	    if( !$this->endAt || !$this->startAt )
		    return null;

    	$today = new \DateTime();
    	$today->setTime(0,0);

    	if( $today <= $this->endAt && $today >= $this->startAt )

	    if( $addTime ){

		    $period = explode(' ', $this->getSchedule());

		    if( count($period) > 1)
			    $hours = explode('-', $period[1]);
		    else
			    $hours = explode('-', $period[0]);

		    $endHours = explode('h', strtolower($hours[1]));

		    if( count($endHours) == 2 )
			    $today->setTime(intval($endHours[0]), intval($endHours[1]));
		    else
			    $today->setTime(intval($endHours[0]), 0);
	    }

        return $today;
    }

    /**
     * @param $endAt
     * @return $this
     * @throws Exception
     */
    public function setEndAt($endAt): self
    {
        $this->endAt = $this->formatDateTime($endAt);

        return $this;
    }

    public function getSeatingCapacity(): ?int
    {
        return $this->seatingCapacity;
    }

    public function setSeatingCapacity($seatingCapacity): self
    {
        $this->seatingCapacity = $this->formatInt($seatingCapacity, -1);

        return $this;
    }

    public function getRegistrantsCount(): ?int
    {
        return $this->registrantsCount;
    }

    public function setRegistrantsCount($registrantsCount): self
    {
        $this->registrantsCount = $this->formatInt($registrantsCount);

        return $this;
    }

    public function getRemainingPlaces(): ?int
    {
        return max(0, $this->remainingPlaces);
    }

    public function setRemainingPlaces($remainingPlaces): self
    {
        $this->remainingPlaces = $this->formatInt($remainingPlaces, -1);

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

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function getHours(): ?int
    {
        if( $formation = $this->getFormation() )
            return $formation->getHours();

        return 0;
    }

    public function getHoursEthics(): ?int
    {
        if( $formation = $this->getFormation() )
            return $formation->getHoursEthics();

        return 0;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;

        return $this;
    }

    public function setFormationId(?int $formation_id): self
    {
        $formation = new Formation();
        $formation->setId($formation_id);

        $this->setFormation($formation);

        return $this;
    }

    /**
     * @return Collection|FormationParticipant[]
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(FormationParticipant $participant): self
    {
        if (!$this->participants->contains($participant)) {
            $this->participants[] = $participant;
            $participant->setFormationCourse($this);
        }

        return $this;
    }

    public function removeParticipant(FormationParticipant $participant): self
    {
        if ($this->participants->contains($participant)) {
            $this->participants->removeElement($participant);
            // set the owning side to null (unless already changed)
            if ($participant->getFormationCourse() === $this) {
                $participant->setFormationCourse(null);
            }
        }

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): self
    {
        switch ($format){
            case 'Présentielle':
            case 'formation en présentiel collectif':
            case 'Formation en présentiel collectif':
                $format = 'instructor-led';
                break;
            case 'En agence':
                $format = 'in-house';
                break;
            case 'A distance (E-learning)':
            case 'formation à distance asynchrone':
            case 'Formation à distance asynchrone':
                $format = 'e-learning';
                break;
            case 'Webinar':
            case 'Webinaire':
            case 'formation à distance synchrone':
            case 'Formation à distance synchrone':
            $format= 'webinar';
            break;
            default:
                $format = NULL;
                break;
        }

        $this->format = $format;

        return $this;
    }

    /**
     * @return Contact[]|null
     */
    public function getInstructors($filter=true): ?array
    {
        $instructors = [];

        if( (!$filter || $this->getFormat() !== 'webinar') && $instructor1 = $this->getInstructor1() )
            $instructors[] = $instructor1;

        if($instructor2 = $this->getInstructor2() )
            $instructors[] = $instructor2;

        if($instructor3 = $this->getInstructor3() )
            $instructors[] = $instructor3;

        return array_filter($instructors);
    }

    /**
     * @return string[]|null
     */
    public function getAllInstructors(): ?array
    {
        $instructors = [];

        foreach ($this->getInstructors(false) as $instructor)
            $instructors[$instructor->getId()] = trim($instructor->getFirstname().' '.$instructor->getLastname());

        return $instructors;
    }

	public function getInstructor1(): ?Contact
	{
		return $this->instructor1;
	}

    public function setInstructor1(?Contact $instructor1): self
    {
        $this->instructor1 = $instructor1;

        return $this;
    }

    public function setInstructor1Id(?int $instructor_id): self
    {
        if( $instructor_id ){

            $contact = new Contact();
            $contact->setId($instructor_id);

            $this->setInstructor1($contact);
        }

        return $this;
    }

    public function getInstructor2(): ?Contact
    {
        return $this->instructor2;
    }

    public function getInstructorById($id): ?Contact
    {
        foreach ($this->getInstructors() as $instructor ){

        	if( $instructor->getId() == $id )
        		return $instructor;
        }

        return null;
    }

    public function setInstructor2(?Contact $instructor2): self
    {
        $this->instructor2 = $instructor2;

        return $this;
    }

    public function setInstructor2Id(?int $instructor_id): self
    {
        if( $instructor_id ){

            $contact = new Contact();
            $contact->setId($instructor_id);

            $this->setInstructor2($contact);
        }

        return $this;
    }

    public function getInstructor3(): ?Contact
    {
        return $this->instructor3;
    }

    public function setInstructor3(?Contact $instructor3): self
    {
        $this->instructor3 = $instructor3;

        return $this;
    }

    public function setInstructor3Id(?int $instructor_id): self
    {
        if( $instructor_id ){

            $contact = new Contact();
            $contact->setId($instructor_id);

            $this->setInstructor3($contact);
        }

        return $this;
    }

    public function getTaxRate(): ?float
    {
        return $this->taxRate;
    }

    public function setTaxRate($taxRate): self
    {
        switch ($taxRate){
            case 'TVA N': $taxRate = 0.2; break;
            case 'TVA R': $taxRate = 0.055; break;
            case 'TVA DOM': $taxRate = 0.085; break;
            default: $taxRate = 0; break;
        }

        $this->taxRate = $taxRate;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getWebinarId(): ?string
    {
        return $this->webinarId;
    }

    public function setWebinarId(?string $webinarId): self
    {
        $this->webinarId = $webinarId;

        return $this;
    }

    public function getHasEdit(): ?bool
    {
        return $this->hasEdit;
    }

    public function setHasEdit($hasEdit): self
    {
        $this->hasEdit = $this->formatBool($hasEdit);

        return $this;
    }

    public function getResendMail(): ?bool
    {
        return $this->resendMail;
    }

    public function setResendMail($resendMail): self
    {
        $this->resendMail = $this->formatBool($resendMail);

        return $this;
    }

    public function getEditNote(): ?string
    {
        return $this->editNote;
    }

    public function setEditNote(?string $editNote): self
    {
        $this->editNote = $this->formatText($editNote);

        return $this;
    }
}
