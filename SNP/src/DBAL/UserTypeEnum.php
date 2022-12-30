<?php

namespace App\DBAL;

class UserTypeEnum extends EnumType
{
    protected $name = self::class;
    protected $values = [null,'contact','company','collaborator','commercial_agent','legal_representative','student'];
}