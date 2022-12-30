<?php

namespace App\DBAL;

class NewsTargetEnum extends EnumType
{
    protected $name = self::class;
    protected $values = [null,'all','app','extranet'];
}