<?php

namespace App\Form;

use App\Entity\CategorieDocument;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategorieDocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', ChoiceType::class, [
                'label' => 'Type de catégorie',
                'choices' => [
                    'Ordonnances' => 'ORD',
                    'Analyses biologiques' => 'ANA',
                    'Imagerie médicale' => 'IMG',
                    'Comptes rendus médicaux' => 'CRM',
                    'Certificats médicaux' => 'CER',
                    'Vaccinations' => 'VAC',
                    'Hospitalisation' => 'HOS',
                    'Autres documents' => 'AUT',
                ],
                'placeholder' => 'Choisir un type',
                'required' => true,
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 3],
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CategorieDocument::class,
        ]);
    }
}
