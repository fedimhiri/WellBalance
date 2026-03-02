<?php

namespace App\Form;

use App\Entity\RendezVous;
use App\Entity\TypeRendezVous;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RendezVousPatientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => self::fieldAttr([
                    'placeholder' => 'Motif du rendez-vous',
                ], 'required|minLength:3|maxLength:120'),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => self::fieldAttr([
                    'rows' => 4,
                    'placeholder' => 'Precisions complementaires',
                ], 'required|minLength:3|maxLength:2000'),
            ])
            ->add('dateRdv', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => self::fieldAttr([], 'required|dateFuture'),
            ])
            ->add('heureRdv', TimeType::class, [
                'label' => 'Heure',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => self::fieldAttr([], 'required|timeFormat'),
            ])
            ->add('typeRendezVous', EntityType::class, [
                'class' => TypeRendezVous::class,
                'choice_label' => 'nomType',
                'label' => 'Type de rendez-vous',
                'placeholder' => 'Choisir un type',
                'required' => true,
                'attr' => self::fieldAttr([], 'selectRequired'),
            ])
            ->add('medecin', EntityType::class, [
                'class' => User::class,
                'query_builder' => static fn (UserRepository $repository) => $repository->createMedecinsQueryBuilder(),
                'choice_label' => static fn (User $user) => self::buildUserLabel($user),
                'label' => 'Medecin',
                'placeholder' => 'Choisir un medecin',
                'required' => true,
                'attr' => self::fieldAttr([], 'selectRequired'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RendezVous::class,
        ]);
    }

    private static function buildUserLabel(User $user): string
    {
        $roles = $user->getRoles();
        $roleLabel = 'Utilisateur';

        if (in_array('ROLE_MEDECIN', $roles, true)) {
            $roleLabel = 'Medecin';
        } elseif (in_array('ROLE_PATIENT', $roles, true)) {
            $roleLabel = 'Patient';
        }

        $fullName = $user->getDisplayName();

        return sprintf('%s: %s - %s', $roleLabel, $fullName, (string) $user->getEmail());
    }

    private static function fieldAttr(array $attr, string $rules): array
    {
        $attr['data-validate-rules'] = $rules;

        return $attr;
    }
}
