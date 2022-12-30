<?php

namespace App\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class FormationCourseSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('type', ChoiceType::class, ['choices' => ['webinarId'],
	            'constraints' => [
		            new Assert\NotBlank()
	            ]])
	        ->add('id', TextType::class, [
	        	'constraints' => [
	        		new Assert\Length(['min' => 3])
		        ]
	        ]);

		$builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
	}

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
