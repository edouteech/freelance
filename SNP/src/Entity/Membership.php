<?php

namespace App\Entity;

use DateTime;
use DateTimeInterface;
use Symfony\Component\Serializer\Annotation\Groups;


class Membership
{
	protected $id;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $holder;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $company;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $company_type;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $creation;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $civility;

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
	private $phone;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $address;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $postal_code;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $subject;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $city;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $rcp;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $cashless_transactions;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $cash_transactions;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $first_request_cash_transactions;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $amount_cash_transactions;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $rental_management;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $first_request_rental_management;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $amount_rental_management;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $trustee;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $first_request_trustee;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $amount_trustee;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $source;

	/**
	 * @Groups({"eudonet","insert"})
	 */
	private $origin='origin_web';

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }


	/**
	 * @param $value
	 * @param int $empty
	 * @return int
	 */
	protected function formatInt($value, $empty=0 ){

		if( empty($value) )
			return $empty;

		if( is_string($value) )
			$value = str_replace(' ','', $value);

		return intval($value);
	}

	/**
	 * @param $value
	 * @param bool $allowNull
	 * @return boolean
	 */
	protected function formatBool($value, $allowNull=false){

		if( is_null($value) && $allowNull )
			return null;

		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * @param $value
	 * @return string
	 */
	protected function formatText($value){

		if( is_string($value) ){

			$value = str_replace("<br>", "\n", str_replace("<br/>", "\n", str_replace("<br />", "\n", $value)));
			return trim(strip_tags(html_entity_decode($value)));
		}

		return NULL;
	}

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getHolder()
    {
        return $this->holder;
    }

    /**
     * @return mixed
     */
    public function getOrigin()
    {
        return $this->origin;
    }

    /**
     * @param mixed $holder
     */
    public function setHolder($holder): void
    {
        $this->holder = $holder;
    }

    /**
     * @return mixed
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * @param mixed $company
     */
    public function setCompany($company): void
    {
        $this->company = $this->formatText($company);
    }

    /**
     * @return mixed
     */
    public function getCompanyType()
    {
        return $this->company_type;
    }

    /**
     * @param mixed $company_type
     */
    public function setCompanyType($company_type): void
    {
        $this->company_type = $this->formatText($company_type);
    }

    /**
     * @return mixed
     */
    public function getCreation()
    {
        return $this->creation;
    }

    /**
     * @param mixed $creation
     */
    public function setCreation($creation): void
    {
        $this->creation = $this->formatBool($creation);
    }

    /**
     * @return mixed
     */
    public function getCivility()
    {
        return $this->civility;
    }

    /**
     * @param mixed $civility
     */
    public function setCivility($civility): void
    {
        $this->civility = $this->formatText($civility);
    }

    /**
     * @return mixed
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * @param mixed $lastname
     */
    public function setLastname($lastname): void
    {
        $this->lastname = $this->formatText($lastname);
    }

    /**
     * @return mixed
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * @param mixed $firstname
     */
    public function setFirstname($firstname): void
    {
        $this->firstname = $this->formatText($firstname);
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email): void
    {
        $this->email = $this->formatText($email);
    }

    /**
     * @return mixed
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param mixed $phone
     */
    public function setPhone($phone): void
    {
        $this->phone = $this->formatText($phone);
    }

    /**
     * @return mixed
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param mixed $address
     */
    public function setAddress($address): void
    {
        $this->address = $this->formatText($address);
    }

    /**
     * @return mixed
     */
    public function getPostalCode()
    {
        return $this->postal_code;
    }

    /**
     * @param mixed $postal_code
     */
    public function setPostalCode($postal_code): void
    {
        $this->postal_code = $this->formatText($postal_code);
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param mixed $subject
     */
    public function setSubject($subject): void
    {
        $this->subject = $this->formatText($subject);
    }

    /**
     * @return mixed
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param mixed $city
     */
    public function setCity($city): void
    {
        $this->city = $this->formatText($city);
    }

    /**
     * @return mixed
     */
    public function getRcp()
    {
        return $this->rcp;
    }

    /**
     * @param mixed $rcp
     */
    public function setRcp($rcp): void
    {
        $this->rcp = $rcp;
    }

    /**
     * @return mixed
     */
    public function getCashlessTransactions()
    {
        return $this->cashless_transactions;
    }

    /**
     * @param mixed $cashless_transactions
     */
    public function setCashlessTransactions($cashless_transactions): void
    {
        $this->cashless_transactions = $this->formatBool($cashless_transactions);
    }

    /**
     * @return mixed
     */
    public function getCashTransactions()
    {
        return $this->cash_transactions;
    }

    /**
     * @param mixed $cash_transactions
     */
    public function setCashTransactions($cash_transactions): void
    {
        $this->cash_transactions = $this->formatBool($cash_transactions);
    }

    /**
     * @return mixed
     */
    public function getFirstRequestCashTransactions()
    {
        return $this->first_request_cash_transactions;
    }

    /**
     * @param mixed $first_request_cash_transactions
     */
    public function setFirstRequestCashTransactions($first_request_cash_transactions): void
    {
        $this->first_request_cash_transactions = $this->formatBool($first_request_cash_transactions);
    }

    /**
     * @return mixed
     */
    public function getAmountCashTransactions()
    {
        return $this->amount_cash_transactions;
    }

    /**
     * @param mixed $amount_cash_transactions
     */
    public function setAmountCashTransactions($amount_cash_transactions): void
    {
        $this->amount_cash_transactions = $this->formatInt($amount_cash_transactions);
    }

    /**
     * @return mixed
     */
    public function getRentalManagement()
    {
        return $this->rental_management;
    }

    /**
     * @param mixed $rental_management
     */
    public function setRentalManagement($rental_management): void
    {
        $this->rental_management = $this->formatBool($rental_management);
    }

    /**
     * @return mixed
     */
    public function getFirstRequestRentalManagement()
    {
        return $this->first_request_rental_management;
    }

    /**
     * @param mixed $first_request_rental_management
     */
    public function setFirstRequestRentalManagement($first_request_rental_management): void
    {
        $this->first_request_rental_management = $this->formatBool($first_request_rental_management);
    }

    /**
     * @return mixed
     */
    public function getAmountRentalManagement()
    {
        return $this->amount_rental_management;
    }

    /**
     * @param mixed $amount_rental_management
     */
    public function setAmountRentalManagement($amount_rental_management): void
    {
        $this->amount_rental_management = $this->formatInt($amount_rental_management);
    }

    /**
     * @return mixed
     */
    public function getTrustee()
    {
        return $this->trustee;
    }

    /**
     * @param mixed $trustee
     */
    public function setTrustee($trustee): void
    {
        $this->trustee = $this->formatBool($trustee);
    }

    /**
     * @return mixed
     */
    public function getFirstRequestTrustee()
    {
        return $this->first_request_trustee;
    }

    /**
     * @param mixed $first_request_trustee
     */
    public function setFirstRequestTrustee($first_request_trustee): void
    {
        $this->first_request_trustee = $this->formatBool($first_request_trustee);
    }

    /**
     * @return mixed
     */
    public function getAmountTrustee()
    {
        return $this->amount_trustee;
    }

    /**
     * @param mixed $amount_trustee
     */
    public function setAmountTrustee($amount_trustee): void
    {
        $this->amount_trustee = $this->formatInt($amount_trustee);
    }

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param mixed $source
     */
    public function setSource($source): void
    {
        $this->source = $this->formatText($source);
    }
}
