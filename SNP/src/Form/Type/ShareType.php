<?php

namespace App\Form\Type;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ShareType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('emails', CollectionType::class, [
				'entry_type' => EmailType::class,
				'allow_add' => true,
				'entry_options' => [
					'constraints' => [
						new Assert\NotBlank(),
						new Assert\Email(['mode'=>'html5']),
						new Assert\Length(['max' => 60])
					]
				],
			])->add('subject', TextType::class, [
				'constraints' => [
					new Assert\NotBlank(),
					new Assert\Length(['min' => 2, 'max' => 60])
				]
			])->add('type', ChoiceType::class, [
				'choices' => ['document','formation','page','news']
			])->add('id', IntegerType::class, [
				'constraints' => [
					new Assert\NotBlank()
				]
			])->add('message', TextareaType::class, [
				'constraints' => [
					new Assert\NotBlank(),
					new Assert\Length(['min' => 10]),
				]
			]);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
