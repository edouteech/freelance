<?php

namespace App\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InstructorReadType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('updatedAt', DateTimeType::class, [
				'widget' => 'single_text',
				'view_timezone'=> 'Europe/Paris'
			])
			->add('createdAt', DateTimeType::class, [
				'widget' => 'single_text',
				'view_timezone'=> 'Europe/Paris'
			]);

		$builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(['csrf_protection' => false]);
	}
}
