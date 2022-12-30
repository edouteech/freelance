<?php

namespace App\Form\Type;

use App\Entity\ContactMetadata;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MetadataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('state', ChoiceType::class, ['choices' => ['read', 'favorite', 'pinned']])
            ->add('type', ChoiceType::class, ['choices' => ['resource', 'appendix', 'tour']])
            ->add('entityId', TextType::class);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ContactMetadata::class,
	        'csrf_protection' => false
        ]);
    }
}
