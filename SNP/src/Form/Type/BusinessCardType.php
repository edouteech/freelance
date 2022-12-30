<?php

namespace App\Form\Type;

use App\Entity\CompanyBusinessCard;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BusinessCardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder
		    ->add('kind', ChoiceType::class, ['multiple' => true, 'choices' => ['Gestion', 'Syndic', 'Transaction'], 'empty_data'=>'Gestion'])
		    ->add('number', TextType::class, [
			    'constraints' => [
				    new Assert\NotBlank(),
				    new Assert\Length(['min' => 3]),
			    ]
		    ])
		    ->add('issuedAt',DateType::class, [
			    'widget' => 'single_text'
		    ])
		    ->add('cci', NumberType::class, [
			    'constraints' => [
				    new Assert\NotBlank()
			    ]
		    ]);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => CompanyBusinessCard::class,
	        'csrf_protection' => false
        ]);
    }
}
