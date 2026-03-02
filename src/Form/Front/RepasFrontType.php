<?php

namespace App\Form\Front;

use App\Entity\Repas;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RepasFrontType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeRepas', ChoiceType::class, [
                'label' => 'Type de repas',
                'choices' => [
                    'Petit-déjeuner' => 'Petit-déjeuner',
                    'Déjeuner' => 'Déjeuner',
                    'Dîner' => 'Dîner',
                    'Collation' => 'Collation',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('calories', IntegerType::class, [
                'label' => 'Calories',
                'attr' => ['class' => 'form-control', 'min' => 0],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ])
            ->add('dateRepas', DateTimeType::class, [
                'label' => 'Date du repas',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Repas::class,
        ]);
    }
}
