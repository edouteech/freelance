<?php

namespace App\Form\Type;

use App\Entity\Contact;
use App\Entity\Formation;
use App\Entity\FormationCourse;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class FormationCourseCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('schedule', TextType::class, [
                'constraints' => [
				    new Assert\Regex("/^\d{2}(H|h)\d{0,2}\-\d{2}(H|h)\d{0,2}$/")
			    ]
            ])
            ->add('startAt', DateType::class, [
                'widget' => 'single_text'
            ])
            ->add('endAt', DateType::class, [
                'widget' => 'single_text'
            ])
            ->add('seatingCapacity', IntegerType::class)
            ->add('formation', EntityType::class, [
                'class' => Formation::class
            ])
            ->add('instructor1', EntityType::class, [
                'class' => Contact::class
            ])
            ->add('instructor2', EntityType::class, [
                'class' => Contact::class
            ])
            ->add('instructor3', EntityType::class, [
                'class' => Contact::class
            ])
            ->add('taxRate', NumberType::class)
            ->add('days', NumberType::class)
            ->add('format', ChoiceType::class, [
                'choices' => ['instructor-led','in-house','e-learning','webinar']
            ])
            ->add('status', ChoiceType::class, [
                'choices' => ['completed','canceled','potential','confirmed','delayed']
            ]);

		$builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
	}

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => FormationCourse::class,
            'csrf_protection' => false]);
    }
}
