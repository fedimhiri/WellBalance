<?php

namespace App\Form;

use App\Entity\SuiviNutrition;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SuiviNutritionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ✅ POUR TESTER DANS LE SITE : on choisit la date du suivi
            ->add('dateSuivi', DateTimeType::class, [
                'label' => 'Date du suivi',
                'widget' => 'single_text',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])

            ->add('poids', NumberType::class, [
                'label' => 'Poids (kg)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.1',
                    'min' => 0,
                ],
            ])

            ->add('respectPourcentage', IntegerType::class, [
                'label' => 'Respect du plan (%)',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 0,
                    'max' => 100,
                ],
            ])

            ->add('humeur', ChoiceType::class, [
                'label' => 'Humeur',
                'required' => false,
                'choices' => [
                    'Bien' => 'Bien',
                    'Moyen' => 'Moyen',
                    'Mal' => 'Mal',
                    'Stressé' => 'Stressé',
                    'Fatigué' => 'Fatigué',
                ],
                'placeholder' => 'Choisir...',
                'attr' => ['class' => 'form-select'],
            ])

            ->add('commentairePatient', TextareaType::class, [
                'label' => 'Commentaire patient',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SuiviNutrition::class,
        ]);
    }
}
