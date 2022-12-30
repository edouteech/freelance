<?php

namespace App\DBAL;

class RcpTypeEnum extends EnumType
{
    protected $name = self::class;
    protected $values = [null, 'no', 'asseris', 'other'];
}