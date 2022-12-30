<?php

namespace App\Entity;

use App\DBAL\RoleEnum;
use Swagger\Annotations as SWG;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TermRepository")
 */
class Term extends AbstractEntity
{
	/**
	 * @ORM\Id()
	 * @ORM\Column(type="integer")
	 */
	protected $id;

    /**
     * @SWG\Property(example="Mandat")
     * @ORM\Column(type="string", length=255)
     */
	private $title;

    /**
     * @SWG\Property(example="mandat")
     * @ORM\Column(type="string", length=255)
     */
    private $slug;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $parent;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $depth=0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $count=0;

    /**
     * @ORM\Column(type="integer", nullable=true, name="`order`"))
     */
    private $order=0;

    /**
     * @ORM\Column(type="string", length=100)
     */
    private $taxonomy;

	/**
	 * @ORM\Column(type=RoleEnum::class)
	 */
	//todo: revoir la gestion des roles
	protected $role='ROLE_USER';

	/**
	 * @ORM\Column(type="string", columnDefinition="ENUM('expert')", nullable=true, name="`function`")
	 */
	protected $function;

	public static $functions = ['expert'];

    public function setId(int $id): self
    {
        $this->id = $id;

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

	public function getRole(): ?string
   	{
   		return $this->role;
   	}

	public function setRole(string $role): self
   	{
   		$this->role = 'ROLE_'.strtoupper($role);
   
   		return $this;
   	}

	public function getFunction(): ?string
   	{
   		return $this->function;
   	}

	public function setFunction(?string $function): self
   	{
   		$this->function = empty($function)?null:$function;
   
   		return $this;
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

    public function getParent(): ?int
    {
        return $this->parent;
    }

    public function setParent(int $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getDepth(): ?int
    {
        return $this->depth;
    }

    public function setDepth(int $depth): self
    {
        $this->depth = $depth;

        return $this;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function setOrder(int $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): self
    {
        $this->count = $count;

        return $this;
    }

    public function getTaxonomy(): ?string
    {
        return $this->taxonomy;
    }

    public function setTaxonomy(string $taxonomy): self
    {
    	if( $taxonomy == 'post_tag' )
		    $taxonomy = 'tag';

        $this->taxonomy = $taxonomy;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
