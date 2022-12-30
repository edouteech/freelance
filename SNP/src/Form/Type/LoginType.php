<?php

namespace App\Form\Type;

use App\Entity\Company;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder
		    ->add('company',EntityType::class, ['class' => Company::class])
		    ->add('login', TextType::class, [
			    'constraints' => [
				    new Assert\Length(['max' => 60])
			    ]
		    ])
		    ->add('token', TextType::class)
		    ->add('password', TextType::class, [
			    'constraints' => [
				    new Assert\Length(['max' => 60])
			    ]
		    ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
	        'csrf_protection' => false
        ]);
    }
}
