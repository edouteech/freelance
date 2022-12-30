<?php

namespace App\Form\Type;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AppendixReadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('sort', ChoiceType::class, ['choices' => ['popular', 'createdAt', 'category'], 'empty_data'=>'createdAt'])
	        ->add('order', ChoiceType::class, ['choices' => ['asc','desc'], 'empty_data'=>'desc'])
	        ->add('filter', ChoiceType::class, ['choices' => ['favorite', 'year']])
            ->add('category', ChoiceType::class, ['multiple' => true, 'choices' => ['prime-d-assurance', 'facture', 'attestation', 'devis', 'appel-de-cotisation', 'documents-administratifs', 'garantie-financiere', 'appel-de-prime', 'convention', 'bulletin', 'divers']])
	        ->add('year', IntegerType::class)
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