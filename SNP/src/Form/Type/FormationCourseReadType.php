<?php

namespace App\Form\Type;

use App\Entity\Formation;
use App\Form\DataTransformer\LocationTransformer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class FormationCourseReadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('seat', NumberType::class, ['empty_data'=>'0'])
	        ->add('updatedAt', DateType::class, [
	            'widget' => 'single_text',
                'view_timezone'=> 'Europe/Paris']
            )
	        ->add('startAt', DateType::class, ['widget' => 'single_text'])
            ->add('endAt', DateType::class, ['widget' => 'single_text'])
            ->add('duration', NumberType::class)
            ->add('distance', NumberType::class)
            ->add('sort', ChoiceType::class, ['choices' => ['distance', 'startAt', 'duration', 'ethics', 'discrimination'], 'empty_data'=>'startAt'])
            ->add('order', ChoiceType::class, ['choices' => ['asc','desc'], 'empty_data'=>'asc'])
            ->add('format',ChoiceType::class, ['multiple' => true, 'choices' => ['e-learning', 'instructor-led', 'in-house', 'webinar']])
            ->add('location', TextType::class)
            ->add('theme', TextType::class)
            ->add('formation',EntityType::class, ['class' => Formation::class])
            ->add('ethics', NumberType::class)
            ->add('discrimination', NumberType::class)
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
