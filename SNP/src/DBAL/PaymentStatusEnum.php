<?php

namespace App\DBAL;

class PaymentStatusEnum extends EnumType
{
    protected $name = self::class;
    protected $values = ['captured','authorized','payedout','refunded','unknown','failed','suspended','expired','pending','canceled','new'];
}