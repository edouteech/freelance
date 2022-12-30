<?php

namespace App\Service;

use DateTimeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ValueChecker
{
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    private $errors = [];

	public function getErrors()
	{
		$errors = $this->errors;

		$this->resetErrors();

		return $errors;
	}

    public function addError(string $message)
	{
        $this->errors[] = $this->translator->trans($message);
	}

	private function resetErrors()
	{
		$this->errors = [];
	}

	public function isValid(string $propertyName, $value, array $constraints = []): self
	{
		foreach ($constraints as $constraint) {

			if(method_exists($this, $constraint)) {

				$this->$constraint($propertyName, $value);
			}
		}

		return $this;
	}

	private function isEmpty(string $propertyName, $value): void
	{
		if(is_null($value) || empty($value))
			$this->addError("$propertyName must be not empty");
	}

	private function isValidEmail(string $propertyName, $value): void
	{
        if(is_null($value) || empty($value))
            $this->addError("$propertyName must be not empty");
		elseif( !filter_var($value, FILTER_VALIDATE_EMAIL) )
			$this->addError("$propertyName is not valid");
	}

	private function isValidPhoneNumber(string $propertyName, $value): void
	{
		//todo: check phone number
	}

	private function isValidDate(string $propertyName, $value): void
	{
        if(is_null($value) || empty($value))
            $this->addError("$propertyName must be not empty");
        elseif( !($value instanceof DateTimeInterface) )
			$this->addError("$propertyName is not valid");
	}
}
