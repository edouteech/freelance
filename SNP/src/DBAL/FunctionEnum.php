<?php

namespace App\DBAL;

class FunctionEnum extends EnumType
{
    protected $name = self::class;
    protected $values = [null,'expert'];
}