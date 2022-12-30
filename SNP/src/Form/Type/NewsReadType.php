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

class NewsReadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('sort', ChoiceType::class, ['choices' => ['popular', 'createdAt', 'averageRate'], 'empty_data'=>'createdAt'])
            ->add('target', ChoiceType::class, ['choices' => ['all', 'app', 'extranet'], 'empty_data'=>'createdAt'])
	        ->add('order', ChoiceType::class, ['choices' => ['asc','desc'], 'empty_data'=>'desc'])
	        ->add('category', EntityType::class, [
		        'class' => Term::class,
		        'multiple' => true,
		        'choice_value' => 'slug',
		        'query_builder' => function (EntityRepository $er) {
			        return $er->createQueryBuilder('t')
				        ->where('t.taxonomy = :taxonomy')
				        ->setParameter('taxonomy', 'news_category');
		        },
	        ]);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
