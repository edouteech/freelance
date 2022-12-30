<?php

namespace App\Form\Type;

use App\Entity\FormationParticipantProgress;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class FormationParticipantProgressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('chapter',  NumberType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(['min' => -1, 'max'=>99]),
                ]
            ])
            ->add('subchapter',  NumberType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(['min' => 0, 'max'=>99]),
                ]
            ])
            ->add('scroll',  NumberType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(['min' => 0, 'max'=>1]),
                ]
            ])
            ->add('media',  NumberType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(['min' => 0, 'max'=>1]),
                ]
            ])
            ->add('timeElapsed',  NumberType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(['min' => 0, 'max'=>9999]),
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
	        'data_class' => FormationParticipantProgress::class,
	        'csrf_protection' => false
        ]);
    }
}
