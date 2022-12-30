<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Symfony\Component\Serializer\Annotation\Groups;


class Request
{
	protected $id;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $civility;

	private $type;

	private $title;

	private $memberId;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $lastname;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $firstname;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $email;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $message;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $recipient;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $status='process';

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $subject;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $channel='form_snpi';

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $created_at;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $contact=null;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $company=null;

	private function clean($value){

		$value = str_replace("<br>", "\n", str_replace("<br/>", "\n", str_replace("<br />", "\n", $value)));
		return trim(strip_tags(html_entity_decode($value)));
	}

	public function __construct()
	{
		$this->setCreatedAt(new DateTime());
	}

	public function getId(): ?string
	{
		return $this->id;
	}

	public function getCivility(): ?string
	{
		return $this->civility;
	}

	public function setCivility(?string $civility): self
	{
		$this->civility = $this->clean($civility);

		return $this;
	}

	public function getLastname(): ?string
	{
		return $this->lastname;
	}

	public function setLastname(string $lastname): self
	{
		$this->lastname = $this->clean($lastname);

		return $this;
	}

	public function getFirstname(): ?string
	{
		return $this->firstname;
	}

	public function setFirstname(string $firstname): self
	{
		$this->firstname = $this->clean($firstname);

		return $this;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function setEmail(string $email): self
	{
		$this->email = $this->clean($email);

		return $this;
	}

	public function getMessage(): ?string
	{
		return $this->message;
	}

	public function setMessage(string $message): self
	{
		$this->message = $this->clean($message);

		return $this;
	}

	public function getRecipient(): ?string
	{
		return $this->recipient;
	}

	public function setRecipient(string $recipient): self
	{
		$this->recipient = $this->clean($recipient);

		return $this;
	}

	public function getStatus(): ?string
	{
		return $this->status;
	}

	public function setStatus(string $status): self
	{
		$this->status = $this->clean($status);

		return $this;
	}

	public function getSubject(): ?string
	{
		return $this->subject;
	}

	public function setSubject(string $subject): self
	{
		$this->subject = $this->clean($subject);

		return $this;
	}

	public function getChannel(): ?string
	{
		return $this->channel;
	}

	public function setChannel(string $channel): self
	{
		$this->channel = $this->clean($channel);

		return $this;
	}

	public function getCreatedAt(): ?DateTimeInterface
	{
		return $this->created_at;
	}

	public function setCreatedAt(DateTimeInterface $created_at): self
	{
		$this->created_at = $created_at;

		return $this;
	}

	public function getContact(): ?Contact
	{
		return $this->contact;
	}

	public function setContact(Contact $contact): self
	{
		$this->contact = $contact;

		return $this;
	}

	public function getCompany(): ?Company
	{
		return $this->company;
	}

	public function setCompany(Company $company): self
	{
		$this->company = $company;

		return $this;
	}

	public function getType(): ?string
	{
		return $this->type;
	}

	public function setType(string $type): self
	{
		$this->type = $type;

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

	public function getMemberId(): ?string
	{
		return $this->memberId;
	}

	public function setMemberId(string $memberId): self
	{
		$this->memberId = $memberId;

		return $this;
	}
}
