<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\FormationPriceRepository")
 */
class FormationPrice extends AbstractEudoEntity
{
	/**
	 * @ORM\Column(type="float")
	 */
	private $price;

	/**
	 * @ORM\Column(type="string", length=30, nullable=true)
	 */
	private $mode;

	/**
	 * @ORM\Column(type="string", length=30, nullable=true)
	 */
	private $type;

	/**
     * @ORM\ManyToOne(targetEntity="App\Entity\Formation", inversedBy="prices")
     * @ORM\JoinColumn(nullable=false)
	 */
	private $formation;


	public function getPrice(): ?float
	{
		return $this->price;
	}

	public function setPrice($price): self
	{
		$this->price = $this->formatFloat($price);

		return $this;
	}

	public function getFormation(): ?Formation
	{
		return $this->formation;
	}

	public function setFormation(?Formation $formation): self
	{
		$this->formation = $formation;

		return $this;
	}

	public function setFormationId(int $formation_id): self
	{
		$formation = new Formation();
		$formation->setId($formation_id);

		$this->setFormation($formation);

		return $this;
	}

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type): void
    {
        switch ($type){
            case 'Non Adhérent':
                $type = 'not_member';
                break;
            default:
                $type = 'member';
                break;
        }
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param mixed $mode
     */
    public function setMode($mode): void
    {
        switch ($mode){
            case 'Par stagiaire':
                $mode = 'by_member';
                break;
            case 'Par session':
                $mode = 'by_session';
                break;
            default:
                $mode = NULL;
                break;
        }

        $this->mode = $mode;
    }
}
