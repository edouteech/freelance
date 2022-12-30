<?php

namespace App\Entity;

use Exception;
use App\DBAL\RoleEnum;
use Doctrine\ORM\Mapping\Table;
use Swagger\Annotations as SWG;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ResourceRepository")
 * @Table(uniqueConstraints={@UniqueConstraint(name="search_idx", columns={"slug","dtype"})})
 * @ORM\InheritanceType("JOINED")
 * @DiscriminatorColumn(name="dtype", type="string")
 * @DiscriminatorMap({"news"="News", "document"="Document", "page"="Page", "resource"="Resource", "appendix"="Appendix"})
 */
class Resource extends AbstractEudoEntity
{
    /**
     * @SWG\Property(example="document", enum={"document","page","news","appendix"})
     */
    protected $type;

    /**
     * @SWG\Property(example="Mandat exclusif de vente")
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank()
     */
    protected $title;

    /**
     * @SWG\Property(example="Le mandat de vente exclusif est un contrat signé entre le propriétaire d'un bien à vendre et un agent immobilier.")
     * @ORM\Column(type="text", nullable=true)
     */
    protected $description;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $sticky;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $position;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $views;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Term", orphanRemoval=true, cascade={"persist","merge"}))
     */
    protected $terms;

    /**
     * @SWG\Property(example="mandat-exclusif-de-vente")
     * @ORM\Column(type="string", length=255)
     */
    protected $slug;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $status;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $notified;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $averageRate;

    /**
     * @ORM\OneToMany(targetEntity=Rating::class, mappedBy="resource", orphanRemoval=true)
     */
    private $ratings;


    public function __construct($id=false)
    {
        parent::__construct($id);
        $this->terms = new ArrayCollection();
        $this->ratings = new ArrayCollection();
    }

    public function getServerUrl() : string
    {
        return $_ENV['CMS_URL'];
    }

    /**
     * @param string $modified
     * @return $this
     * @throws Exception
     */
    public function setModified( $modified ): self
    {
        $this->setUpdatedAt($modified);

        return $this;
    }

    /**
     * @param string $date
     * @return $this
     * @throws Exception
     */
    public function setDate( $date ): self
    {
        $this->setCreatedAt($date);

        return $this;
    }

    /**
     * @param int $menu_order
     * @return $this
     */
    public function setMenuOrder( $menu_order ): self
    {
        $this->setPosition($menu_order);

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getSticky(): ?bool
    {
        return $this->sticky;
    }

    public function setSticky($sticky): self
    {
        $this->sticky = $this->formatBool($sticky);

        return $this;
    }

    public function getViews(): ?int
    {
        return $this->views;
    }

    public function setViews(?int $views): self
    {
        $this->views = $views;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @param ?string $taxonomy
     * @param ?array $roles
     * @param ?int $parent
     * @return Collection|Term[]
     */
    public function getTerms($taxonomy=null, $roles=null, $parent=null): Collection
    {
        $terms = new ArrayCollection();

        /** @var Term $term */
        foreach ($this->terms as $term){

            if( (is_null($taxonomy) || $term->getTaxonomy() == $taxonomy) && ( is_null($roles) || in_array($term->getRole(), $roles )) && (is_null($parent) || $term->getParent() == $parent) )
                $terms->add($term);
        }

        return $terms;
    }

    public function addTerm($term): self
    {
        if( is_array($term) )
            $term = $this->denormalize($term, Term::class);

        if( $term instanceof Term && !$this->terms->contains($term) )
            $this->terms[] = $term;

        return $this;
    }


    public function addTerms(array $terms): self
    {
        foreach ($terms as $term)
            $this->addTerm($term);

        return $this;
    }

    public function removeTerm(Term $term): self
    {
        if ($this->terms->contains($term)) {
            $this->terms->removeElement($term);
        }

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getNotified(): ?bool
    {
        return $this->notified;
    }

    public function setNotified(?bool $notified): self
    {
        $this->notified = $notified;

        return $this;
    }

    /**
     * @return Collection|Rating[]
     */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    /**
     * @return float
     */
    public function getAverageRatings(): ?float
    {
        $ratings = $this->getRatings();

        if( !count($ratings) )
            return null;

        $total = 0;

        foreach ($ratings as $rating )
            $total += $rating->getRate();

        return $total/count($ratings);
    }

    public function addRating(Rating $rating): self
    {
        if (!$this->ratings->contains($rating)) {
            $this->ratings[] = $rating;
            $rating->setResource($this);
        }

        return $this;
    }

    public function removeRating(Rating $rating): self
    {
        if ($this->ratings->removeElement($rating)) {
            // set the owning side to null (unless already changed)
            if ($rating->getResource() === $this) {
                $rating->setResource(null);
            }
        }

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAverageRate()
    {
        return $this->averageRate;
    }

    /**
     * @param mixed $averageRate
     */
    public function setAverageRate($averageRate): void
    {
        $this->averageRate = $averageRate;
    }

    /**
     * @return false|string
     */
    public function getDashboardLink(){

        switch ( $this->getType() ){

            case 'document':

                return $_ENV['DASHBOARD_URL'].'/document/'.$this->getSlug();

            case 'page':

                return $_ENV['DASHBOARD_URL'].'/'.$this->getSlug();

            case 'news':

                return $_ENV['DASHBOARD_URL'].'/news/'.$this->getSlug();
        }

        return false;
    }
}
