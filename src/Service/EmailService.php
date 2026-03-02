<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $senderEmail,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendDocument(string $to, Document $document, ?string $projectDir = null): bool
    {
        try {
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($to)
                ->subject('📄 WellBalance - Document médical: ' . $document->getTitre())
                ->html($this->buildHtmlBody($document));

            // Attach file if exists
            $filePath = $this->getFilePath($document, $projectDir);
            if (null !== $filePath && file_exists($filePath)) {
                $filename = basename($filePath);
                $email->attachFromPath($filePath, $filename);
            }

            $this->mailer->send($email);

            $this->logger->info('Email sent successfully', ['to' => $to]);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendSimpleEmail(string $to, string $subject, string $htmlBody): bool
    {
        try {
            $email = (new Email())
                ->from($this->senderEmail)
                ->to($to)
                ->subject($subject)
                ->html($htmlBody);

            $this->mailer->send($email);

            $this->logger->info('Email sent successfully', ['to' => $to]);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function getFilePath(Document $document, ?string $projectDir): ?string
    {
        $relativePath = $document->getCheminFichier();
        if (null === $relativePath) {
            return null;
        }

        if (null !== $projectDir && !str_starts_with($relativePath, '/')) {
            return $projectDir . '/public/' . $relativePath;
        }

        return $relativePath;
    }

    private function buildHtmlBody(Document $document): string
    {
        $summary = $document->getResumeAi() ?? 'Aucun résumé disponible.';
        $typeDocument = $document->getTypeDocument() ?? 'Document';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px; text-align: center;">
        <h1 style="color: white; margin: 0;">🏥 WellBalance Clinic</h1>
    </div>
    <div style="padding: 20px; background: #f9f9f9; border-radius: 10px; margin-top: 20px;">
        <h2 style="color: #333;">Document médical: {$this->escapeHtml($document->getTitre())}</h2>
        <p><strong>Type de document:</strong> <span style="background: #e3f2fd; padding: 5px 10px; border-radius: 5px;">{$this->escapeHtml($typeDocument)}</span></p>
        <hr style="border: 1px solid #ddd; margin: 20px 0;">
        <h3 style="color: #667eea;">📊 Résumé par Intelligence Artificielle</h3>
        <p style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #667eea;">{$this->escapeHtml(nl2br($summary))}</p>
    </div>
    <div style="text-align: center; margin-top: 20px; padding: 20px; color: #666; font-size: 12px;">
        <p>Cet email a été envoyé automatiquement par WellBalance Clinic.</p>
        <p>© 2024 WellBalance - Tous droits réservés</p>
    </div>
</body>
</html>
HTML;
    }

    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
