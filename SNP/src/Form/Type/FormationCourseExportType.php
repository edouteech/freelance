<?php

namespace App\Form\Type;

use App\Entity\Contact;
use App\Entity\Formation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationCourseExportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
	        ->add('startAt', DateType::class, ['widget' => 'single_text'])
            ->add('endAt', DateType::class, ['widget' => 'single_text'])
	        ->add('contact',EntityType::class, ['class' => Contact::class])
	        ->add('formation',EntityType::class, ['class' => Formation::class]);
	}

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
