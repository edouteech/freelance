<?php

namespace App\Form\DataTransformer;

use App\Entity\AbstractEntity;
use Symfony\Component\Form\DataTransformerInterface;
use Doctrine\Common\Collections\ArrayCollection;

class EntitiesTransformer implements DataTransformerInterface
{
	public function transform($value)
	{
		return null;
	}

	/**
	 * @param AbstractEntity[] $value
	 * @return array
	 */
	public function reverseTransform($value)
	{
		$data = [];

		if( $value instanceof ArrayCollection && !$value->isEmpty()){

			foreach ($value->getValues() as $entity)
				$data[] = $entity->getId();
		}

		return $data;
	}
}