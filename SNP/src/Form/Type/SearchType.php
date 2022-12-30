<?php

namespace App\Form\Type;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
	        ->add('limit', RangeType::class, [
		        'attr' => [
			        'min' => 2,
			        'max' => 6
		        ],
		        'empty_data' => 3
	        ])
	        ->add('entity', ChoiceType::class, ['multiple' => true, 'choices' => ['document','appendix','formation','expert','news']])
	        ->add('query', TextType::class, [
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


/*SELECT r0_.title AS title_0, r0_.description AS description_1,
       t2_.id as t2id,
       r0_.sticky AS sticky_2, r0_.position AS position_3, r0_.role AS role_4, r0_.slug AS slug_5, r0_.status AS status_6,
       r0_.id AS id_7, r0_.created_at AS created_at_8, r0_.updated_at AS updated_at_9, r0_.inserted_at AS inserted_at_10,
       d1_.thumbnail AS thumbnail_11, r0_.dtype AS dtype_12
FROM document d1_
INNER JOIN resource r0_ ON d1_.id = r0_.id
LEFT JOIN resource_term r3_ ON r0_.id = r3_.resource_id
LEFT JOIN term t2_ ON t2_.id = r3_.term_id AND t2_.id IN (23,24,25,26,29,35,40,41,42,43,44,45,46,50,51,53,54,55)
WHERE r0_.status = 'publish' AND r0_.title LIKE '%Plaquette du SNPI%' AND (NOT (EXISTS(
        SELECT r0_.title AS title_0
        FROM document d1_
                 INNER JOIN resource r0_ ON d1_.id = r0_.id
                 LEFT JOIN resource_term r3_ ON r0_.id = r3_.resource_id
                 LEFT JOIN term t2_ ON t2_.id = r3_.term_id AND t2_.id IN (30,31,32,36,37,38,39,52)
        WHERE r0_.status = 'publish' AND r0_.title LIKE '%Plaquette du SNPI%' and t2_.id IS NOT NULL
    )))*/