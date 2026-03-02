<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\CategorieDocument;
use App\Entity\Document;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class DocumentAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du document',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Ordonnance Dr. Martin'],
            ])
            ->add('typeDocument', ChoiceType::class, [
                'label' => 'Type de document',
                'choices' => [
                    'Ordonnance' => 'ordonnance',
                    'Facture' => 'facture',
                    'Analyse' => 'analyse',
                    'Rapport médical' => 'rapport',
                    'Certificat' => 'certificat',
                    'Autre' => 'autre',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('categorie', EntityType::class, [
                'class' => CategorieDocument::class,
                'choice_label' => 'nom',
                'required' => false,
                'placeholder' => '-- Sélectionner une catégorie --',
                'label' => 'Catégorie',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('insuranceReference', TextType::class, [
                'label' => 'Référence assurance',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: INS-2025-1234'],
            ])
            ->add('fichier', FileType::class, [
                'label' => 'Fichier PDF',
                'mapped' => false,
                'required' => $options['is_new'],
                'constraints' => array_filter([
                    $options['is_new'] ? new NotBlank(['message' => 'Veuillez sélectionner un fichier PDF']) : null,
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => ['application/pdf'],
                        'mimeTypesMessage' => 'Seuls les fichiers PDF sont autorisés (max 5 Mo)',
                    ]),
                ]),
                'attr' => ['class' => 'form-control', 'accept' => '.pdf'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'is_new' => true,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
