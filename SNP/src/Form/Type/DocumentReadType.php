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

class DocumentReadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('sort', ChoiceType::class, ['choices' => ['popular', 'updatedAt', 'createdAt', 'kind', 'section', 'category', 'title'], 'empty_data'=>'createdAt'])
	        ->add('order', ChoiceType::class, ['choices' => ['asc','desc'], 'empty_data'=>'desc'])
	        ->add('filter', ChoiceType::class, ['choices' => ['favorite','year']])
	        ->add('year', IntegerType::class)
            ->add('category', EntityType::class, [
            	'class' => Term::class,
	            'multiple' => true,
	            'choice_value' => 'slug',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('u')
                        ->where('u.taxonomy = :taxonomy')
                        ->setParameter('taxonomy', 'category');
                },
            ])
	        ->add('search', TextType::class, [
		        'constraints' => [
			        new Assert\Length(['min' => 3])
		        ]
	        ]);

        $entitiesTransformer = new EntitiesTransformer();

	    $builder->get('category')->addModelTransformer($entitiesTransformer);

	    $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['csrf_protection' => false]);
    }
}
