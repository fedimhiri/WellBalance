<?php

namespace App\Form;

use App\Entity\Repas;
use App\Entity\PlanNutrition;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RepasType extends AbstractType
{
    private function getChoicesByObjectif(?string $objectif): array
    {
        $objectif = trim((string) $objectif);

        return match ($objectif) {
            'Perte de poids' => [
                'Repas hypocalorique' => 'Repas hypocalorique',
                'Repas riche en fibres' => 'Repas riche en fibres',
                'Repas protéiné léger' => 'Repas protéiné léger',
                'Collation légère' => 'Collation légère',
                'Hydratation / infusion' => 'Hydratation / infusion',
            ],
            'Prise de masse' => [
                'Repas hypercalorique' => 'Repas hypercalorique',
                'Repas hyperprotéiné' => 'Repas hyperprotéiné',
                'Recharge glucidique' => 'Recharge glucidique',
                'Collation énergétique' => 'Collation énergétique',
                'Shake / supplément (optionnel)' => 'Shake / supplément (optionnel)',
            ],
            'Maintien' => [
                'Repas équilibré' => 'Repas équilibré',
                'Repas riche en protéines' => 'Repas riche en protéines',
                'Repas riche en légumes' => 'Repas riche en légumes',
                'Collation équilibrée' => 'Collation équilibrée',
                'Snack contrôlé' => 'Snack contrôlé',
            ],
            'Régime spécial' => [
                'Sans gluten' => 'Sans gluten',
                'Sans lactose' => 'Sans lactose',
                'Diabétique (IG bas)' => 'Diabétique (IG bas)',
                'Hypo-salé' => 'Hypo-salé',
                'Végétarien / Vegan' => 'Végétarien / Vegan',
            ],
            'Détox' => [
                'Jus / smoothie' => 'Jus / smoothie',
                'Repas léger' => 'Repas léger',
                'Soupe / bouillon' => 'Soupe / bouillon',
                'Hydratation' => 'Hydratation',
                'Fruits / crudités' => 'Fruits / crudités',
            ],
            'Performance sportive' => [
                'Pré-entraînement' => 'Pré-entraînement',
                'Post-entraînement' => 'Post-entraînement',
                'Repas riche en glucides' => 'Repas riche en glucides',
                'Repas riche en protéines' => 'Repas riche en protéines',
                'Hydratation + électrolytes' => 'Hydratation + électrolytes',
            ],
            default => [
                'Repas équilibré' => 'Repas équilibré',
                'Collation' => 'Collation',
                'Hydratation' => 'Hydratation',
            ],
        };
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var PlanNutrition|null $planOption */
        $planOption = $options['plan'] ?? null;

        /** @var Repas|null $repas */
        $repas = $options['data'] ?? null;
        $plan = $planOption ?: ($repas?->getPlanNutrition());

        $objectif = $plan?->getObjectif();
        $choices = $this->getChoicesByObjectif($objectif);

        $builder
            ->add('typeRepas', ChoiceType::class, [
                'label' => 'Type de repas (selon objectif)',
                'choices' => $choices,
                'placeholder' => 'Choisir un type',
                'attr' => ['class' => 'form-select'],
            ])

            // ✅ API input (code-barres)
            ->add('barcode', TextType::class, [
                'label' => 'Code-barres (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 3017620422003',
                ],
            ])

            // ✅ IMPORTANT: portionSize (colonne DB = portion_size)
            ->add('portionSize', TextType::class, [
                'label' => 'Portion (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 100g / 1 portion / 30g',
                ],
            ])

            ->add('calories', IntegerType::class, [
                'label' => 'Calories (kcal)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 5000,
                ],
            ])

            ->add('proteines', NumberType::class, [
                'label' => 'Protéines (g)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'step' => '0.1', 'min' => 0],
            ])
            ->add('glucides', NumberType::class, [
                'label' => 'Glucides (g)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'step' => '0.1', 'min' => 0],
            ])
            ->add('lipides', NumberType::class, [
                'label' => 'Lipides (g)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'step' => '0.1', 'min' => 0],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description des aliments',
                'attr' => ['rows' => 3, 'class' => 'form-control'],
            ])
            ->add('dateRepas', DateTimeType::class, [
                'label' => 'Date et heure du repas',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ]);

        if ($plan) {
            $builder->add('planNutrition', EntityType::class, [
                'label' => 'Plan nutrition associé',
                'class' => PlanNutrition::class,
                'choices' => [$plan],
                'choice_label' => fn (PlanNutrition $p) => $p->getObjectif() . ' - ' . $p->getUser()->getNom(),
                'disabled' => true,
                'attr' => ['class' => 'form-select'],
            ]);
        } else {
            $builder->add('planNutrition', EntityType::class, [
                'label' => 'Plan nutrition associé',
                'class' => PlanNutrition::class,
                'choice_label' => fn (PlanNutrition $p) => $p->getObjectif() . ' - ' . $p->getUser()->getNom(),
                'placeholder' => 'Sélectionner un plan',
                'attr' => ['class' => 'form-select'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Repas::class,
            'plan' => null,
        ]);
    }
}
