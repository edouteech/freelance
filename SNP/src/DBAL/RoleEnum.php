<?php

namespace App\DBAL;

class RoleEnum extends EnumType
{
    protected $name = self::class;
    protected $values = ['ROLE_USER','ROLE_CLIENT','ROLE_COMPANY','ROLE_CONTACT','ROLE_COMMERCIAL_AGENT'];
}