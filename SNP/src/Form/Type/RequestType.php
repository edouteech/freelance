<?php

namespace App\Form\Type;

use App\Entity\Request;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RequestType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
			->add('firstname', TextType::class, [
				'constraints' => [
					new Assert\NotBlank(),
					new Assert\Length(['min' => 2]),
					new Assert\Length(['max' => 60])
				]
			])
			->add('lastname', TextType::class, [
				'constraints' => [
					new Assert\NotBlank(),
					new Assert\Length(['min' => 2, 'max' => 60])
				]
			])
			->add('type', ChoiceType::class, ['choices' => ['contact', 'activation'], 'empty_data'=>'contact'])
			->add('civility', ChoiceType::class, ['choices' => ['Monsieur', 'Madame'], 'empty_data'=>'Madame'])
			->add('email', EmailType::class, [
				'constraints' => [
					new Assert\Email(['mode'=>'html5']),
					new Assert\NotBlank(),
					new Assert\Length(['max' => 60])
				]
			])
			->add('message', TextareaType::class, [
				'constraints' => [
					new Assert\NotBlank(),
					new Assert\Length(['min' => 10]),
				]
			])
			->add('subject', TextType::class)
			->add('recipient', ChoiceType::class, ['choices' => ['juridique_immo', 'juridique_social', 'administratif_snpi', 'administratif_vhs', 'administratif_asseris', 'formation', 'sinistres_vhs', 'sinistres_asseris', 'communication', 'syndicale', 'technique'], 'empty_data'=>'communication']);

		$builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
	}

	public function configureOptions(OptionsResolver $resolver)
	{
		$resolver->setDefaults([
			'data_class' => Request::class,
			'csrf_protection' => false
		]);
	}
}
