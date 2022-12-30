<?php

namespace App\Form\Type;

use App\Entity\User;
use FSevestre\BooleanFormType\Form\Type\BooleanType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder
		    ->add('hasNotification', BooleanType::class)
		    ->add('isAccessible', BooleanType::class);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
	        'csrf_protection' => false
        ]);
    }
}
