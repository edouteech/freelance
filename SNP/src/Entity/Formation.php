<?php

namespace App\Entity;

use Cocur\Slugify\Slugify;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\DBAL\FormationTypeEnum;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FormationRepository")
 */
class Formation extends AbstractEudoEntity
{
    const FORMAT_WEBINAR = 'webinar';
    const FORMAT_IN_HOUSE = 'in-house';
    const FORMAT_E_LEARNING = 'e-learning';
    const FORMAT_INSTRUCTOR_LED = 'instructor-led';

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

    /**
     * @ORM\Column(type="float")
     */
    private $hours;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $days;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     */
    private $code;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isActive;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\FormationCourse", mappedBy="formation", orphanRemoval=true)
     */
    private $courses;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\FormationPrice", mappedBy="formation", orphanRemoval=true)
     */
    private $prices;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"eudonet","update"})
     */
    private $objective;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $hoursEthics=0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $hoursDiscrimination=0;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $job;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $theme;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $theme_slug;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $program;

    /**
     * @ORM\Column(type=FormationTypeEnum::class, nullable=true)
     */
    private $format;

    /**
     * @ORM\OneToOne(targetEntity=FormationFoad::class, mappedBy="formation", fetch="EXTRA_LAZY")
     */
    private $foad;

    /**
     * @ORM\ManyToOne(targetEntity=Formation::class)
     */
    private $previousFormation;

    public function __construct()
    {
        parent::__construct();

        $this->courses = new ArrayCollection();
        $this->prices = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->getTitle()??'';
    }

    public function setVideo($video): self
    {
        if( $foad = $this->getFoad() )
            $foad->setVideo($video);

        return $this;
    }

    public function setDocuments($documents): self
    {
        if( $foad = $this->getFoad() )
            $foad->setDocuments($documents);

        return $this;
    }

    public function setWrite($write): self
    {
        if( $foad = $this->getFoad() )
            $foad->setWrite($write);

        return $this;
    }

    public function setQuiz($quiz): self
    {
        if( $foad = $this->getFoad() )
            $foad->setQuiz($quiz);

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $this->formatString($title);

        return $this;
    }

    public function getHours(): ?float
    {
        return $this->hours;
    }

    public function setHours($hours): self
    {
        $this->hours = $this->formatFloat($hours);

        return $this;
    }

    /**
     * @return Collection|FormationCourse[]
     */
    public function getCourses(): Collection
    {
        return $this->courses;
    }

    public function addCourse(FormationCourse $course): self
    {
        if (!$this->courses->contains($course)) {
            $this->courses[] = $course;
            $course->setFormation($this);
        }

        return $this;
    }

    public function removeCourse(FormationCourse $course): self
    {
        if ($this->courses->contains($course)) {
            $this->courses->removeElement($course);
            // set the owning side to null (unless already changed)
            if ($course->getFormation() === $this) {
                $course->setFormation(null);
            }
        }

        return $this;
    }

    public function getDays(): ?float
    {
        return $this->days;
    }

    public function setDays($days): self
    {
        $this->days = $this->formatFloat($days);

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): self
    {
        $this->theme = $theme;

        $slugify = new Slugify();
        $this->setThemeSlug($slugify->slugify($theme));

        return $this;
    }

    public function getThemeSlug(): ?string
    {
        return $this->theme_slug;
    }

    public function setThemeSlug(?string $theme_slug): self
    {
        $this->theme_slug = $theme_slug;

        return $this;
    }

    public function getProgram(): ?string
    {
        return $this->program;
    }

    public function setProgram(?string $program): self
    {
        $this->program = $program;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->getIsActive();
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive($isActive): self
    {
        $this->isActive = $this->formatBool($isActive);

        return $this;
    }

    /**
     * @return Collection|FormationPrice[]
     */
    public function getPrices(): Collection
    {
        return $this->prices;
    }

    /**
     * @param string $type
     * @return float|null
     */
    public function getPrice($type=false): ?float
    {
        if( !$type ){

            $type = 'member';

            if( defined('CURRENT_USER_TYPE') && CURRENT_USER_TYPE == User::$student )
                $type = 'not_member';
        }

        foreach ($this->getPrices() as $price ){

            if( $price->getType() == $type && $price->getMode() == 'by_member')
                return $price->getPrice();
        }

        if( $type != 'member')
            return $this->getPrice('member');

        return null;
    }

    public function addPrice(FormationPrice $price): self
    {
        if (!$this->prices->contains($price)) {
            $this->prices[] = $price;
            $price->setFormation($this);
        }

        return $this;
    }

    public function removePrice(FormationPrice $price): self
    {
        if ($this->prices->contains($price)) {
            $this->prices->removeElement($price);
            // set the owning side to null (unless already changed)
            if ($price->getFormation() === $this) {
                $price->setFormation(null);
            }
        }

        return $this;
    }

    public function getObjective(): ?string
    {
        return $this->objective;
    }

    public function setObjective(?string $objective): self
    {
        $this->objective = $this->formatText($objective);

        return $this;
    }

    public function getHoursEthics(): ?float
    {
        return $this->hoursEthics;
    }

    public function setHoursEthics($hoursEthics): self
    {
        $this->hoursEthics = $this->formatInt($hoursEthics);

        return $this;
    }

    public function getHoursDiscrimination(): ?float
    {
        return $this->hoursDiscrimination;
    }

    public function setHoursDiscrimination($hoursDiscrimination): self
    {
        $this->hoursDiscrimination = $this->formatInt($hoursDiscrimination);

        return $this;
    }

    public function getJob(): ?string
    {
        return $this->job;
    }

    public function setJob(?string $job): self
    {
        $this->job = $job;

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
                $format = 'instructor-led';
                break;
            case 'En agence':
                $format = 'in-house';
                break;
            case 'A distance (E-learning)':
                $format = 'e-learning';
                break;
            case 'Webinar':
            case 'Webinaire':
            $format= 'webinar';
            break;
            default: $format = NULL; break;
        }

        $this->format = $format;

        return $this;
    }

    public function getPreviousFormation(): ?self
    {
        return $this->previousFormation;
    }

    public function setPreviousFormation(?self $previousFormation): self
    {
        $this->previousFormation = $previousFormation;

        return $this;
    }

    public function setPreviousFormationId(?int $previousFormationId): self
    {
        if( !$previousFormationId )
            return $this;

        $previousFormation = new Formation();
        $previousFormation->setId($previousFormationId);

        $this->previousFormation = $previousFormation;

        return $this;
    }

    public function getFoad(): ?FormationFoad
    {
        return $this->foad;
    }

    public function setProgress(FormationFoad $foad): self
    {
        // set the owning side of the relation if necessary
        if ($foad->getFormation() !== $this) {
            $foad->setFormation($this);
        }

        $this->foad = $foad;

        return $this;
    }

    public function setFoad(?FormationFoad $foad): self
    {
        // unset the owning side of the relation if necessary
        if ($foad === null && $this->foad !== null) {
            $this->foad->setFormation(null);
        }

        // set the owning side of the relation if necessary
        if ($foad !== null && $foad->getFormation() !== $this) {
            $foad->setFormation($this);
        }

        $this->foad = $foad;

        return $this;
    }
}
