<?php

namespace App\Controller\Api;

use App\Service\PdfReportService;
use App\Service\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/stats/medecin', name: 'api_stats_medecin_')]
class StatsReportController extends AbstractController
{
    #[Route('/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(
        Request $request,
        StatsService $statsService,
        PdfReportService $pdfReportService
    ): Response {
        $validation = $this->validateMainStatsQuery($request);
        if (!$validation['valid']) {
            return $this->json([
                'success' => false,
                'message' => 'Parametres invalides.',
                'errors' => $validation['errors'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $medecinId = $validation['medecinId'];
        $from = $validation['from'];
        $to = $validation['to'];

        $stats = $statsService->getMedecinPdfReportData($medecinId, $from, $to);
        $generatedAt = new \DateTimeImmutable('now');
        $pdfBinary = $pdfReportService->generateStatsReportPdf(
            $medecinId,
            $from,
            $to,
            $generatedAt,
            $stats
        );

        $filename = sprintf(
            'stats_medecin_%d_%s_%s.pdf',
            $medecinId,
            $from->format('Y-m-d'),
            $to->format('Y-m-d')
        );

        $response = new Response($pdfBinary, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename)
        );
        $response->headers->set('Content-Length', (string) strlen($pdfBinary));

        return $response;
    }

    /**
     * @return array{
     *   valid: bool,
     *   errors: array<string, string>,
     *   medecinId?: int,
     *   from?: \DateTimeImmutable,
     *   to?: \DateTimeImmutable
     * }
     */
    private function validateMainStatsQuery(Request $request): array
    {
        $errors = [];
        $medecinId = $this->parsePositiveInt($request->query->get('medecinId'));
        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'));

        if (null === $medecinId) {
            $errors['medecinId'] = 'Le parametre medecinId est obligatoire et doit etre un entier positif.';
        }

        if (null === $from) {
            $errors['from'] = 'Le parametre from est obligatoire au format YYYY-MM-DD.';
        }

        if (null === $to) {
            $errors['to'] = 'Le parametre to est obligatoire au format YYYY-MM-DD.';
        }

        if ($from instanceof \DateTimeImmutable && $to instanceof \DateTimeImmutable && $from > $to) {
            $errors['period'] = 'La date from doit etre inferieure ou egale a la date to.';
        }

        if ([] !== $errors) {
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
            'medecinId' => $medecinId,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            false === $date
            || ($errors['warning_count'] ?? 0) > 0
            || ($errors['error_count'] ?? 0) > 0
        ) {
            return null;
        }

        return $date;
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
    }
}
