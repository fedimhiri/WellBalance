<?php

namespace App\Form;

use App\Entity\RendezVous;
use App\Entity\TypeRendezVous;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('dateRdv', DateTimeType::class, [
                'label' => 'Date & heure',
                'widget' => 'single_text',
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Planifié' => RendezVous::STATUT_PLANIFIE,
                    'Terminé'  => RendezVous::STATUT_TERMINE,
                    'Annulé'   => RendezVous::STATUT_ANNULE,
                ],
            ])
            ->add('type', EntityType::class, [
                'label' => 'Type de rendez-vous',
                'class' => TypeRendezVous::class,
                'choice_label' => 'libelle', // ✅ affichage propre
                'placeholder' => 'Choisir un type',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
