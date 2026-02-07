<?php

namespace App\Form;

use App\Entity\PlanNutrition;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanNutritionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('objectif', ChoiceType::class, [
                'label' => 'Objectif du plan',
                'choices' => [
                    'Perte de poids' => 'Perte de poids',
                    'Prise de masse' => 'Prise de masse',
                    'Maintien' => 'Maintien',
                    'Régime spécial' => 'Régime spécial',
                    'Détox' => 'Détox',
                    'Performance sportive' => 'Performance sportive',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('user', EntityType::class, [
                'label' => 'Utilisateur',
                'class' => User::class,
                'choice_label' => function (User $user) {
                    return $user->getNom() . ' (' . $user->getEmail() . ')';
                },
                'placeholder' => 'Sélectionner un utilisateur',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('periode', TextType::class, [
                'label' => 'Période (calculée automatiquement)',
                'required' => false,
                'mapped' => false,   // IMPORTANT: on la calcule côté JS, et côté controller si tu veux
                'attr' => [
                    'readonly' => true,
                    'class' => 'form-control',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanNutrition::class,
        ]);
    }
}
