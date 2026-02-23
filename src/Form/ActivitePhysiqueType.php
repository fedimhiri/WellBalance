<?php

namespace App\Form;

use App\Entity\ActivitePhysique;
use App\Entity\ObjectifSportif;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivitePhysiqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'activité',
                'attr' => ['class' => 'form-control']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('typeActivite', TextType::class, [
                'label' => 'Type d\'activité',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Cardio, Musculation']
            ])
            ->add('niveau', ChoiceType::class, [
                'label' => 'Niveau',
                'choices' => [
                    'Débutant' => 'Débutant',
                    'Intermédiaire' => 'Intermédiaire',
                    'Avancé' => 'Avancé',
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('dureeEstimee', IntegerType::class, [
                'label' => 'Durée estimée (minutes)',
                'attr' => ['class' => 'form-control']
            ])
            ->add('caloriesEstimees', IntegerType::class, [
                'label' => 'Calories estimées',
                'attr' => ['class' => 'form-control']
            ])
            ->add('actif', CheckboxType::class, [
                'label' => 'Actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label']
            ])
            ->add('objectifSportif', EntityType::class, [
                'class' => ObjectifSportif::class,
                'choice_label' => function(ObjectifSportif $objectif) {
                    $user = $objectif->getUser();
                    return $objectif->getLibelle() . ($user ? ' (' . $user->getNom() . ')' : '');
                },
                'label' => 'Objectif associé',
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ActivitePhysique::class,
        ]);
    }
}
