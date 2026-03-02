<?php

namespace App\Form;

use App\Entity\Message;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNewConversation = $options['is_new_conversation'] ?? false;
        $availableAdmins = $options['available_admins'] ?? [];

        if ($isNewConversation) {
            // Champ sujet (seulement pour nouvelle conversation)
            $builder->add('sujet', TextType::class , [
                'label' => 'Sujet (optionnel)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Suivi médical, Question sur traitement...'
                ]
            ]);

            // Champ medecin_id avec les administrateurs disponibles
            $choices = [];
            foreach ($availableAdmins as $admin) {
                $choices[$admin->getUserIdentifier() ?: 'Admin #' . $admin->getId()] = $admin->getId();
            }

            $builder->add('medecin_id', ChoiceType::class , [
                'label' => 'Médecin',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez choisir un médecin.'
                    ])
                ],
                'choices' => $choices,
                'placeholder' => 'Choisir un médecin',
            ]);

            // Champ conversation_type
            $builder->add('conversation_type', ChoiceType::class , [
                'label' => 'Type de conversation',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez choisir un type de conversation.'
                    ])
                ],
                'choices' => [
                    'Normal' => 'normal',
                    'Urgence' => 'urgence',
                    'Consultation' => 'consultation',
                    'Suivi' => 'suivi',
                ],
                'placeholder' => 'Type de conversation',
            ]);
        }

        // Champ content (toujours présent, optionnel si une pièce jointe est jointe)
        $builder->add('content', TextareaType::class , [
            'label' => 'Message',
            'required' => false,
            'attr' => [
                'rows' => 4,
                'class' => 'form-control',
                'placeholder' => 'Votre message...',
            ],
        ]);

        // Champ pièce jointe
        $builder->add('attachment', FileType::class , [
            'label' => 'Pièce jointe',
            'mapped' => false,
            'required' => false,
            'attr' => [
                'accept' => 'image/*,.pdf,.doc,.docx,.xls,.xlsx',
                'class' => 'd-none', // On le cachera en JS pour utiliser un bouton personnalisé
                'id' => 'message_attachment',
            ],
            'constraints' => [
                new File([
                    'maxSize' => '10M',
                    'mimeTypes' => [
                        'image/*',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ],
                    'mimeTypesMessage' => 'Veuillez uploader une image ou un document valide (PDF, Word, Excel)',
                ])
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Message::class ,
            'is_new_conversation' => false,
            'available_admins' => [], // Ajoutez cette option
        ]);

        // Définir que cette option est autorisée
        $resolver->setAllowedTypes('available_admins', 'array');
    }
}