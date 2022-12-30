<?php

namespace App\Form\Type;

use App\Entity\Contact;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder
		    ->add('type', ChoiceType::class, ['choices' => ['formation', 'signature', 'membership_vhs', 'register', 'membership_caci', 'membership_snpi', 'membership_asseris'], 'empty_data'=>'formation'])
		    ->add('productId', NumberType::class)
		    ->add('contacts',EntityType::class, ['class' => Contact::class, 'multiple' => true]);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
	        'csrf_protection' => false
        ]);
    }
}
