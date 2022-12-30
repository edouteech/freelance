<?php

namespace App\DBAL;

class NewsLinkTypeEnum extends EnumType
{
    protected $name = self::class;
    protected $values = [null,'page','external','document','article'];
}
