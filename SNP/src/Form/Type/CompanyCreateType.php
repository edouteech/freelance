<?php

namespace App\Form\Type;

use App\Entity\Company;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CompanyCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder
            ->add('kind', ChoiceType::class, ['multiple' => true, 'choices' => ['Gestion', 'Syndic', 'Transaction'], 'empty_data'=>'Gestion'])
            ->add('siren', TextType::class, [
			    'constraints' => [
				    new Assert\NotBlank(),
				    new Assert\Length(['min' => 9, 'max' => 9]),
			    ]
		    ])
		    ->add('name', TextType::class, [
			    'constraints' => [
				    new Assert\NotBlank(),
				    new Assert\Length(['min' => 2]),
			    ]
		    ])
		    ->add('street', TextType::class, [
			    'constraints' => [
				    new Assert\NotBlank(),
				    new Assert\Length(['min' => 3,'max' => 60])
			    ]
		    ])
		    ->add('zip', TextType::class, [
			    'constraints' => [
				    new Assert\NotBlank(),
				    new Assert\Length(['min' => 3, 'max'=>10])
			    ]
		    ])
		    ->add('city', TextType::class, [
			    'constraints' => [
				    new Assert\NotBlank(),
				    new Assert\Length(['min' => 2, 'max' => 50])
			    ]
		    ]);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
	        'csrf_protection' => false
        ]);
    }
}
