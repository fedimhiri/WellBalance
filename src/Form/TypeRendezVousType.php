<?php

namespace App\Form;

use App\Entity\TypeRendezVous;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TypeRendezVousType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomType', TextType::class, [
                'label' => 'Nom du type',
                'attr' => self::fieldAttr([
                    'placeholder' => 'Consultation generale',
                ], 'required|minLength:3|maxLength:100'),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => self::fieldAttr([
                    'rows' => 4,
                    'placeholder' => 'Description',
                ], 'required|minLength:3|maxLength:2000'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TypeRendezVous::class,
        ]);
    }

    private static function fieldAttr(array $attr, string $rules): array
    {
        $attr['data-validate-rules'] = $rules;

        return $attr;
    }
}
