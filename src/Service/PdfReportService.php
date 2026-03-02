<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfReportService
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * @param array<string, mixed> $stats
     */
    public function generateStatsReportPdf(
        int $medecinId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        \DateTimeImmutable $generatedAt,
        array $stats
    ): string {
        $html = $this->twig->render('pdf/stats_report.html.twig', [
            'medecinId' => $medecinId,
            'from' => $from,
            'to' => $to,
            'generatedAt' => $generatedAt,
            'stats' => $stats,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
