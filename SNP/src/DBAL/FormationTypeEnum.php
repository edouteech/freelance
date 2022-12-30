<?php

namespace App\DBAL;

class FormationTypeEnum extends EnumType
{
    protected $name = self::class;
    protected $values = [null,'instructor-led','in-house','e-learning','webinar','live'];
}