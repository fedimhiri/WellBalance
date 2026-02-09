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

class RendezVous1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class)
            ->add('dateRdv', DateTimeType::class, [
                'widget' => 'single_text',
            ])
            ->add('lieu', TextType::class, [
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
            ])
            ->add('statut', ChoiceType::class, [
                'choices' => [
                    'Planifié' => RendezVous::STATUT_PLANIFIE,
                    'Terminé'  => RendezVous::STATUT_TERMINE,
                    'Annulé'   => RendezVous::STATUT_ANNULE,
                ],
            ])
            ->add('type', EntityType::class, [
                'class' => TypeRendezVous::class,
                'placeholder' => '— Choisir un type —',
                // grâce à __toString() de TypeRendezVous => affiche libelle
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }
}
