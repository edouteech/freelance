<?php

namespace App\Form\Type;

use App\Entity\Address;
use App\Entity\Company;
use FSevestre\BooleanFormType\Form\Type\BooleanType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder
		    ->add('company',EntityType::class, ['class' => Company::class])
		    ->add('street', TextType::class, [
			    'constraints' => [
				    new Assert\Length(['max' => 60])
			    ]
		    ])
		    ->add('zip', TextType::class, [
                'constraints' => [
                    new Assert\Length(['min' => 3, 'max'=>10])
                ]
            ])
		    ->add('city', TextType::class, [
			    'constraints' => [
				    new Assert\Length(['max' => 50])
			    ]
		    ])
            ->add('country', TextType::class, [
                'constraints' => [
                    new Assert\EqualTo('France')
                ]
            ])
		    ->add('email', EmailType::class, [
			    'constraints' => [
				    new Assert\Email(['mode'=>'html5']),
				    new Assert\NotBlank(),
				    new Assert\Length(['max' => 60])
			    ]
		    ])
		    ->add('issuedAt', DateType::class, ['widget' => 'single_text'])
		    ->add('startedAt', DateType::class, ['widget' => 'single_text'])
		    ->add('phone', TelType::class)
		    ->add('position', ChoiceType::class, ['choices' => ['Négociateur immobilier', 'Agent commercial','Autre collaborateur','Gérant','Président','PDG','DG']])
		    ->add('positions', TextType::class)
		    ->add('isHome', BooleanType::class)
		    ->add('isMain', BooleanType::class)
		    ->add('isActive', BooleanType::class)
            ->add('hasCertificate', BooleanType::class);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
	        'csrf_protection' => false
        ]);
    }
}
