<?php

namespace App\Form\Type;

use App\Entity\Formation;
use App\Entity\FormationFoad;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class FormationFoadUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
	        ->add('quiz', TextType::class, [
		        'constraints' => [
	        		new Assert\Json()
		        ]
	        ])
	        ->add('write', TextType::class, [
		        'constraints' => [
	        		new Assert\Json()
		        ]
	        ])
	        ->add('video', TextType::class, [
		        'constraints' => [
	        		new Assert\Json()
		        ]
	        ])
	        ->add('documents', TextType::class, [
		        'constraints' => [
	        		new Assert\Json()
		        ]
	        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
	        'data_class' => FormationFoad::class,
	        'csrf_protection' => false
        ]);
    }
}
