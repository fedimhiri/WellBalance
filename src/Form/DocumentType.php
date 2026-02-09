<?php

namespace App\Form;

use App\Entity\CategorieDocument;
use App\Entity\Document;
use App\Repository\CategorieDocumentRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $fileConstraints = [
            new File([
                'maxSize' => '5M',
                'mimeTypes' => [
                    'application/pdf',
                    'image/png',
                    'image/jpeg',
                ],
                'mimeTypesMessage' => 'Veuillez envoyer un fichier PDF, PNG ou JPEG (max 5 Mo).',
            ]),
        ];

        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du document',
            ])
            ->add('typeDocument', TextType::class, [
                'label' => 'Type de document',
            ])
            ->add('categorie', EntityType::class, [
                'label' => 'Catégorie',
                'class' => CategorieDocument::class,
                'choice_label' => 'description',
                'placeholder' => 'Choisir une catégorie',
                'required' => true, // set to false if you want it optional
                'query_builder' => function (CategorieDocumentRepository $repo) {
                    return $repo->createQueryBuilder('c')
                                ->orderBy('c.description', 'ASC');
                },
            ])
            ->add('fichier', FileType::class, [
                'label' => $isEdit ? 'Nouveau fichier (laisser vide pour conserver l\'actuel)' : 'Fichier',
                'mapped' => false,
                'required' => !$isEdit,
                'constraints' => $fileConstraints,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}