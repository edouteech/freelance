<?php

namespace App\Normalizer;

use App\Entity\AbstractEudoEntity;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class EntityNormalizer implements NormalizerInterface
{
    public function normalize($object, $format = null, array $context = [])
    {
        return $object->getId();
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof AbstractEudoEntity;
    }
}
