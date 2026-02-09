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
use Symfony\Component\Validator\Constraints\NotBlank;

class MessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNewConversation = $options['is_new_conversation'] ?? false;
        $availableAdmins = $options['available_admins'] ?? [];

        if ($isNewConversation) {
            // Champ sujet (seulement pour nouvelle conversation)
            $builder->add('sujet', TextType::class, [
                'label' => 'Sujet (optionnel)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Suivi médical, Question sur traitement...'
                ]
            ]);
            
            // Champ doctor_id avec les administrateurs disponibles
            $choices = [];
            foreach ($availableAdmins as $admin) {
                $choices[$admin->getUsername() ?: 'Admin #' . $admin->getId()] = $admin->getId();
            }
            
            $builder->add('doctor_id', ChoiceType::class, [
                'label' => 'Docteur',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez choisir un docteur.'
                    ])
                ],
                'choices' => $choices,
                'placeholder' => 'Choisir un docteur',
            ]);
            
            // Champ conversation_type
            $builder->add('conversation_type', ChoiceType::class, [
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

        // Champ content (toujours présent)
        $builder->add('content', TextareaType::class, [
            'label' => 'Message',
            'required' => false,
            'attr' => [
                'rows' => 4,
                'class' => 'form-control',
                'placeholder' => 'Votre message...',
            ],
            'constraints' => [
                new NotBlank([
                    'message' => 'Le message ne peut pas être vide. Veuillez écrire quelque chose.'
                ])
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Message::class,
            'is_new_conversation' => false,
            'available_admins' => [], // Ajoutez cette option
        ]);
        
        // Définir que cette option est autorisée
        $resolver->setAllowedTypes('available_admins', 'array');
    }
}