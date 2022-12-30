<?php

namespace App\DBAL;

class UserAccessLogTypeEnum extends EnumType
{
    protected $name = self::class;
    protected $values = ['login','logout','refresh'];
}