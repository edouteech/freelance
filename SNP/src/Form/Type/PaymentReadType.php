<?php

namespace App\Form\Type;

use App\Entity\Term;
use App\Form\DataTransformer\EntitiesTransformer;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PaymentReadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('sort', ChoiceType::class, ['choices' => ['id','status','updated_at','total_amount'], 'empty_data'=>'id'])
	        ->add('order', ChoiceType::class, ['choices' => ['asc','desc'], 'empty_data'=>'desc'])
	        ->add('entity', ChoiceType::class, ['choices' => ['asseris','snpi','vhs',''], 'empty_data'=>'']);


	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
