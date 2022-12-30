<?php

namespace App\DBAL;

class OrderTypeEnum extends EnumType
{
    protected $name = self::class;
    protected $values = ['formation','membership_snpi','membership_vhs','membership_asseris','membership_caci','register','signature'];
}
