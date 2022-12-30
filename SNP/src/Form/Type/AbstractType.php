<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType as SymfonyAbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormEvent;

class AbstractType extends SymfonyAbstractType
{
    public function preSubmit(FormEvent $event){

	    $form = $event->getForm();
	    $data = $event->getData();

	    foreach($form->all() as $child)
	    {
		    $childName = $child->getName();
		    $childType = $child->getConfig()->getType()->getInnerType();

		    //convert any string as formatted date
		    if(isset($data[$childName]) && !empty($data[$childName])){

			    if( get_class($childType) == DateType::class && is_string($data[$childName]) ){

				    $date = strtotime(str_replace('/', '-', $data[$childName]));
				    $data[$childName] = date('Y-m-d', $date);
			    }
			    elseif( get_class($childType) == DateTimeType::class && is_string($data[$childName]) ){

				    $date = strtotime(str_replace('/', '-', $data[$childName]));
				    $data[$childName] = date('Y-m-d h:i:s', $date);
			    }
			    elseif( get_class($childType) == EmailType::class && is_string($data[$childName]) ){

				    $data[$childName] = strtolower($data[$childName]);
			    }
		    }
	    }

	    $event->setData($data);
    }
}
