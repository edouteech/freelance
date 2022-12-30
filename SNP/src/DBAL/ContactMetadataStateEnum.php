<?php

namespace App\DBAL;

class ContactMetadataStateEnum extends EnumType
{
    protected $name = self::class;
    protected $values = ['favorite','read','pinned'];
}