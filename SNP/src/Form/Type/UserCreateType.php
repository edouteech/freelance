<?php

namespace App\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Assert\Email(['mode'=>'html5']),
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 60])
                ]
            ])
            ->add('password', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(['max' => 60])
                ]
            ])
            ->add('type', ChoiceType::class, ['choices' => ['vhs', 'snpi','caci']]);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
	        'csrf_protection' => false
        ]);
    }
}
