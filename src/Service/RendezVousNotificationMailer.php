<?php

namespace App\Service;

use App\Entity\RendezVous;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class RendezVousNotificationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $fromAddress = 'no-reply@wellbalance.tn'
    ) {
    }

    public function sendAccepted(RendezVous $rdv): bool
    {
        return $this->sendWithTemplate(
            $rdv,
            'Votre rendez-vous est accepte',
            'emails/rendez_vous_accepted.html.twig',
            'emails/rendez_vous_accepted.txt.twig',
            'ACCEPTE'
        );
    }

    public function sendRefused(RendezVous $rdv): bool
    {
        return $this->sendWithTemplate(
            $rdv,
            'Votre rendez-vous est refuse',
            'emails/rendez_vous_refused.html.twig',
            'emails/rendez_vous_refused.txt.twig',
            'REFUSE'
        );
    }

    private function sendWithTemplate(
        RendezVous $rdv,
        string $subject,
        string $htmlTemplate,
        string $textTemplate,
        string $statusLabel
    ): bool {
        $patient = $rdv->getPatient();
        $patientEmail = $patient?->getEmail();

        if (null === $patient || null === $patientEmail || '' === trim($patientEmail)) {
            $this->logger->warning('Email RDV non envoye: patient ou email absent.', [
                'rdvId' => $rdv->getId(),
                'status' => $statusLabel,
            ]);

            return false;
        }

        $medecin = $rdv->getMedecin();
        $medecinNom = trim((string) ($medecin?->getDisplayName() ?? ''));
        if ('' === $medecinNom) {
            $medecinNom = (string) ($medecin?->getEmail() ?? '—');
        }

        $context = [
            'rdv' => $rdv,
            'patient' => $patient,
            'statusLabel' => $statusLabel,
            'message' => null,
            'medecinNomComplet' => $medecinNom,
        ];

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, 'WellBalance'))
            ->to($patientEmail)
            ->subject($subject)
            ->htmlTemplate($htmlTemplate)
            ->text($this->twig->render($textTemplate, $context))
            ->context($context);

        try {
            $this->mailer->send($email);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur envoi email notification RDV.', [
                'rdvId' => $rdv->getId(),
                'status' => $statusLabel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
