<?php

namespace App\Entity;

use App\Repository\InstructorRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=InstructorRepository::class)
 */
class Instructor extends AbstractEudoEntity
{
    /**
     * @ORM\ManyToOne(targetEntity=Contact::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $contact;

    /**
     * @ORM\ManyToOne(targetEntity=Formation::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $formation;

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

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;

        return $this;
    }

    public function setFormationId(?int $formation_id): self
    {
        if( $formation_id ){

            $formation = new Formation();
            $formation->setId($formation_id);

            $this->setFormation($formation);
        }

        return $this;
    }
}
