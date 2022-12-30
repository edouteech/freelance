<?php

namespace App\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationReadType extends AbstractType
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
			])
			->add('sort', ChoiceType::class, ['choices' => ['createdAt', 'id'], 'empty_data'=>'id'])
			->add('order', ChoiceType::class, ['choices' => ['asc','desc'], 'empty_data'=>'asc'])
		;

		$builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults(['csrf_protection' => false]);
	}
}
