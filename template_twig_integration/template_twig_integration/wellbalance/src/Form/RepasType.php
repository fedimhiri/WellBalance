<?php

namespace App\Form;

use App\Entity\Repas;
use App\Entity\PlanNutrition;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RepasType extends AbstractType
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
                    'En-cas' => 'En-cas',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('calories', IntegerType::class, [
                'label' => 'Calories',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 5000,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description des aliments',
                'attr' => ['rows' => 3],
            ])
            ->add('dateRepas', DateTimeType::class, [
                'label' => 'Date et heure du repas',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control datetimepicker'],
            ])
            ->add('planNutrition', EntityType::class, [
                'label' => 'Plan nutrition associé',
                'class' => PlanNutrition::class,
                'choice_label' => function(PlanNutrition $plan) {
                    return $plan->getObjectif() . ' - ' . $plan->getUser()->getNom();
                },
                'placeholder' => 'Sélectionner un plan',
                'attr' => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Repas::class,
        ]);
    }
}