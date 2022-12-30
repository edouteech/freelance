<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class LocationTransformer implements DataTransformerInterface
{
	public function transform($value)
	{
		if( is_array($value) )
			return implode(',', $value);

		return null;
	}

	/**
	 * @param  $value
	 * @return array
	 */
	public function reverseTransform($value)
	{
		if( is_string($value) ){

			$value = explode(',', $value);

			if( count($value) == 2 )
				return [floatval($value[0]), floatval($value[1])];
		}

		return null;
	}
}