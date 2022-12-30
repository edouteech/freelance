<?php

namespace App\Form\Type;

use App\Entity\Contact;
use FSevestre\BooleanFormType\Form\Type\BooleanType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ContactUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder
		    ->add('firstname', TextType::class, [
			    'constraints' => [
				    new Assert\NotBlank(),
				    new Assert\Length(['min' => 2, 'max' => 60])
			    ]
		    ])
		    ->add('lastname', TextType::class, [
			    'constraints' => [
				    new Assert\NotBlank(),
				    new Assert\Length(['min' => 2, 'max' => 60])
			    ]
		    ])
		    ->add('password', TextType::class)
		    ->add('civility', ChoiceType::class, ['choices' => ['Monsieur', 'Madame'], 'empty_data'=>'Madame'])
		    ->add('avatar', FileType::class, [
			    'mapped' => false,
			    'constraints' => [
				    new Assert\Image([
					    'detectCorrupted' => true,
					    'minWidth' => 50,
					    'minHeight' => 50,
					    'maxHeight' => 2000,
					    'maxWidth' => 2000
				    ])
			    ]
		    ])
		    ->add('birthday', DateType::class, [
			    'widget' => 'single_text'
		    ])
		    ->add('hasDashboard', BooleanType::class)
		    ->add('addresses', CollectionType::class,[
			    'entry_type' => AddressType::class,
			    'allow_add' => false,
			    'by_reference' => false
		    ]);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
	        'csrf_protection' => false
        ]);
    }
}
