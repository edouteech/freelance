<?php

namespace App\Form\Type;

use App\Entity\Company;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CompanyUpdateType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('phone', TelType::class)
	        ->add('email', EmailType::class, [
		        'constraints' => [
			        new Assert\Email(['mode'=>'html5']),
			        new Assert\Length(['max' => 60])
		        ]
	        ])
	        ->add('sales', IntegerType::class)
	        ->add('turnover', ChoiceType::class, ['choices' => ['turnover1', 'turnover2','turnover3', 'turnover4']])
	        ->add('website', UrlType::class)
	        ->add('facebook', UrlType::class)
	        ->add('logo', FileType::class, [
		        'mapped' => false,
		        'constraints' => [
			        new Assert\Image([
				        'detectCorrupted' => true,
				        'minWidth' => 50,
				        'minHeight' => 50,
				        'maxWidth' => 2000,
				        'maxHeight' => 2000
			        ])
		        ]
	        ])
	        ->add('twitter', UrlType::class);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
	        'csrf_protection' => false
        ]);
    }
}
