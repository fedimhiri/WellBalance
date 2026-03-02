<?php

namespace App\Service;

use App\Entity\Message;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class MailerService
{
    private MailerInterface $mailer;
    private string $senderEmail;
    private string $senderName;

    public function __construct(MailerInterface $mailer, string $senderEmail = 'no-reply@clinique.com', string $senderName = 'Clinique Médicale')
    {
        $this->mailer = $mailer;
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
    }

    public function sendEmergencyNotification(Message $message): void
    {
        $conversation = $message->getConversation();
        $medecin = $conversation->getMedecin();
        $patient = $message->getSender();

        if (!$medecin || !$medecin->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($medecin->getEmail(), $medecin->getUserIdentifier()))
            ->subject('URGENCE : Nouveau message de ' . $patient->getUserIdentifier())
            ->htmlTemplate('emails/emergency_notification.html.twig')
            ->context([
                'message' => $message,
                'patient' => $patient,
                'medecin' => $medecin,
                'conversation' => $conversation,
            ]);

        // Route spécifiquement vers le transport messenger_mailer
        $email->getHeaders()->addTextHeader('X-Transport', 'messenger_mailer');

        $this->mailer->send($email);
    }

    public function sendNewMessageNotification(Message $message, string $recipientRole): void
    {
        $conversation = $message->getConversation();
        
        if ($recipientRole === 'ROLE_ADMIN') {
            $recipient = $conversation->getMedecin();
            $sender = $conversation->getUser();
        } else {
            $recipient = $conversation->getUser();
            $sender = $conversation->getMedecin();
        }

        if (!$recipient || !$recipient->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(new Address($recipient->getEmail(), $recipient->getUserIdentifier()))
            ->subject('Nouveau message de ' . $sender->getUserIdentifier())
            ->htmlTemplate('emails/new_message_notification.html.twig')
            ->context([
                'message' => $message,
                'sender' => $sender,
                'recipient' => $recipient,
                'conversation' => $conversation,
            ]);

        // Route spécifiquement vers le transport messenger_mailer
        $email->getHeaders()->addTextHeader('X-Transport', 'messenger_mailer');

        $this->mailer->send($email);
    }
}
