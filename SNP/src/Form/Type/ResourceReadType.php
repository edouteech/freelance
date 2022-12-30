<?php

namespace App\Form\Type;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ResourceReadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('sort', ChoiceType::class, ['choices' => ['createdAt'], 'empty_data'=>'createdAt'])
	        ->add('order', ChoiceType::class, ['choices' => ['asc','desc'], 'empty_data'=>'desc'])
	        ->add('filter', ChoiceType::class, ['choices' => ['favorite']])
            ->add('category', TextType::class)
            ->add('search', TextType::class, [
	            'constraints' => [
		            new Assert\Length(['min' => 3])
	            ]
            ]);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
