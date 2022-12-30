<?php

namespace App\DBAL;

class ContactMetadataTypeEnum extends EnumType
{
    protected $name = self::class;
    protected $values = ['resource','appendix','tour'];
}