<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Exception;

/**
 * @ORM\Entity(repositoryClass="App\Repository\OptionRepository")
 * @ORM\Table(name="`option`")
 */
class Option
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $name;

    /**
     * @ORM\Column(type="text")
     */
    private $value;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $expire;

    /**
     * @ORM\Column(type="boolean")
     */
    private $public=0;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $type;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getPublic(): ?bool
    {
        return $this->public;
    }

    public function setPublic(?bool $public): self
    {
        $this->public = $public;

        return $this;
    }

    public function getValue()
    {
	    $value = @unserialize($this->value);
        return $value !== false ? $value : $this->value;
    }

    public function setValue($value): self
    {
        $this->value = is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;

        return $this;
    }

    public function getExpire(): ?DateTimeInterface
    {
        return $this->expire;
    }

    public function isExpired()
    {
    	if( $this->expire )
		    return new DateTime() > $this->expire;

    	return false;
    }

    public function setExpire($expire): self
    {
    	if( $expire ){

		    if( is_string($expire) ){

			    try {
				    $expire = new DateTime($expire);
			    } catch (Exception $e) {
				    $expire = false;
			    }
		    }

		    if( $expire )
		    	$this->expire = $expire;
	    }

        return $this;
    }

    public function getType(): ?string
    {
	    return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
