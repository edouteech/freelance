<?php

namespace App\Form\Type;

use App\Entity\Contact;
use App\Entity\ExternalFormation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ExternalFormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder
		    ->add('title', TextType::class, [
			    'constraints' => [
				    new Assert\NotBlank(),
				    new Assert\Length(['min' => 3]),
			    ]
		    ])
		    ->add('contact',EntityType::class, ['class' => Contact::class])
		    ->add('address', TextType::class)
		    ->add('hours', NumberType::class, [
			    'constraints' => [
				    new Assert\NotBlank(),
				    new Assert\Range(['min' => 1, 'max'=>42]),
			    ]
		    ])
		    ->add('hoursEthics', NumberType::class, [
			    'constraints' => [
				    new Assert\Range(['min' => 0, 'max'=>10]),
			    ]
		    ])
		    ->add('hoursDiscrimination', NumberType::class, [
			    'constraints' => [
				    new Assert\Range(['min' => 0, 'max'=>10]),
			    ]
		    ])
		    ->add('certificate', FileType::class, [
			    'mapped' => false,
			    'constraints' => [
				    new Assert\File([
					    'maxSize' => '10240k',
					    'mimeTypes' => [
						    'application/pdf',
						    'application/x-pdf',
					    ],
					    'mimeTypesMessage' => 'Please upload a valid PDF document',
				    ])
			    ]
		    ])
		    ->add('startAt', DateType::class, ['widget' => 'single_text'])
		    ->add('endAt', DateType::class, ['widget' => 'single_text'])
		    ->add('format', ChoiceType::class, ['choices' => ['instructor-led', 'in-house', 'e-learning']]);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ExternalFormation::class,
	        'csrf_protection' => false
        ]);
    }
}
