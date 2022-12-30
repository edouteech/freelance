<?php

namespace App\Form\Type;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CompanyFeeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
	    $builder
		   ->add('file', FileType::class, [
			    'mapped' => false,
			    'constraints' => [
				    new Assert\File([
					    'mimeTypes'=>"application/pdf"
				    ])
			    ]
		    ]);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
	        'csrf_protection' => false
        ]);
    }
}
