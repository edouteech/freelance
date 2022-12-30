<?php

namespace App\Form\Type;

use App\Entity\Address;
use FSevestre\BooleanFormType\Form\Type\BooleanType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AddressUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder->add('email', EmailType::class, [
			    'constraints' => [
				    new Assert\Email(['mode'=>'html5']),
				    new Assert\NotBlank(),
				    new Assert\Length(['max' => 60])
			    ]
		    ])
		    ->add('phone', TelType::class)
		    ->add('isMain', BooleanType::class)
		    ->add('issuedAt',DateType::class, ['widget' => 'single_text'])
		    ->add('isActive', BooleanType::class)
		    ->add('hasCertificate', BooleanType::class);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
	        'csrf_protection' => false
        ]);
    }
}
