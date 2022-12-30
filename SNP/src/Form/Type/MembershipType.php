<?php

namespace App\Form\Type;

use App\Entity\Membership;
use FSevestre\BooleanFormType\Form\Type\BooleanType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class MembershipType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
            ->add('holder', ChoiceType::class, ['multiple'=>true, 'choices' => ['holder_transactions', 'holder_rental_management', 'holder_syndic', 'holder_none'], 'empty_data'=>'holder_none'])
            ->add('company', TextType::class, [
				'constraints' => [
					new Assert\NotBlank(),
					new Assert\Length(['min' => 2]),
					new Assert\Length(['max' => 60])
				]
			])
            ->add('company_type', ChoiceType::class, ['choices' => ['individual_company', 'regular_company']])
            ->add('creation', BooleanType::class)
            ->add('civility', ChoiceType::class, ['choices' => ['Madame', 'Monsieur']])
            ->add('lastname', TextType::class, [
				'constraints' => [
					new Assert\NotBlank(),
					new Assert\Length(['min' => 2, 'max' => 60])
				]
			])
            ->add('firstname', TextType::class, [
				'constraints' => [
					new Assert\NotBlank(),
					new Assert\Length(['min' => 2, 'max' => 60])
				]
			])
            ->add('phone', TelType::class)
			->add('email', EmailType::class, [
				'constraints' => [
					new Assert\Email(['mode'=>'html5']),
					new Assert\NotBlank(),
					new Assert\Length(['max' => 60])
				]
			])
            ->add('address', TextType::class, [
                'constraints' => [
                    new Assert\Length(['min' => 2, 'max' => 60])
                ]
            ])
            ->add('postal_code', TextType::class, [
                'constraints' => [
                    new Assert\Length(['min' => 5, 'max' => 6])
                ]
            ])
            ->add('city', TextType::class, [
                'constraints' => [
                    new Assert\Length(['min' => 2, 'max' => 60])
                ]
            ])
			->add('rcp', ChoiceType::class, ['multiple'=>true, 'choices' => ['rcp_transactions', 'rcp_rental_management', 'rcp_syndic', 'rcp_expert']])
            ->add('cashless_transactions', BooleanType::class)
            ->add('cash_transactions', BooleanType::class)
            ->add('first_request_cash_transactions', BooleanType::class)
            ->add('amount_cash_transactions', TextType::class, [
                'constraints' => [
                    new Assert\Length(['min' => 2, 'max' => 60])
                ]
            ])
            ->add('rental_management', BooleanType::class)
            ->add('first_request_rental_management', BooleanType::class)
            ->add('amount_rental_management', TextType::class, [
                'constraints' => [
                    new Assert\Length(['min' => 2, 'max' => 60])
                ]
            ])
            ->add('trustee', BooleanType::class)
            ->add('first_request_trustee', BooleanType::class)
            ->add('amount_trustee', TextType::class, [
                'constraints' => [
                    new Assert\Length(['min' => 2, 'max' => 60])
                ]
            ])
            ->add('source', ChoiceType::class, ['choices' => ['source_word_of_mouth', 'source_event', 'source_facebook', 'source_web', 'source_linkedin', 'source_press', 'source_other']])
        ;

		$builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults([
            'data_class' => Membership::class,
			'csrf_protection' => false
		]);
	}
}
