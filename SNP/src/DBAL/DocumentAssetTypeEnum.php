<?php

namespace App\DBAL;

class DocumentAssetTypeEnum extends EnumType
{
    protected $name = self::class;
    protected $values = ['pdf','podcast','image','archive','other','document','editable-pdf'];
}