<?php

namespace App\Form\Type;

use App\Form\DataTransformer\LocationTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AddressReadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('distance', NumberType::class)
            ->add('sort', ChoiceType::class, ['choices' => ['distance', 'startedAt','lastname'], 'empty_data'=>'lastname'])
            ->add('order', ChoiceType::class, ['choices' => ['asc','desc'], 'empty_data'=>'asc'])
            ->add('location', TextType::class)
	        ->add('search', TextType::class, [
		        'constraints' => [
			        new Assert\Length(['min' => 3])
		        ]
	        ]);

	    $builder->get('location')->addModelTransformer(new LocationTransformer());
	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
