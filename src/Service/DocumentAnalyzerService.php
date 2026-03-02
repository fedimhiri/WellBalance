<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

class DocumentAnalyzerService
{
    private const TYPE_KEYWORDS = [
        'facture' => ['facture', 'montant', 'total', 'paiement', 'prix', 'euro', 'ht', 'ttc', 'tva'],
        'ordonnance' => ['ordonnance', 'prescription', 'médicament', 'posologie', 'comprimé', 'gélule', 'sirop'],
        'analyse' => ['analyse', 'résultat', 'laboratoire', 'sang', 'urine', 'biologie', 'taux', 'valeur'],
        'rapport' => ['rapport', 'consultation', 'diagnostic', 'examen', 'clinique', 'observation', 'patient'],
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    private const ANOMALY_KEYWORDS = [
        'anomalie', 'anormal', 'urgence', 'critique', 'pathologique', 'élevé', 'anormalement',
    ];

    /**
     * @return array{resume: string, mots_cles: string[], type_detecte: string, has_anomaly: bool}
     */
    public function analyze(string $text): array
    {
        $cleanedText = $this->cleanText($text);
        $typeDetecte = $this->detectType($cleanedText);
        $motsCles = $this->extractKeywords($cleanedText);
        $resume = $this->generateSummary($cleanedText);

        $hasAnomaly = $this->containsAnomalyKeyword($cleanedText);

        $this->logger->info('Document analyzed', [
            'type_detecte' => $typeDetecte,
            'keywords_count' => \count($motsCles),
            'has_anomaly' => $hasAnomaly,
        ]);

        return [
            'resume' => $resume,
            'mots_cles' => $motsCles,
            'type_detecte' => $typeDetecte,
            'has_anomaly' => $hasAnomaly,
        ];
    }

    private function containsAnomalyKeyword(string $text): bool
    {
        $lower = mb_strtolower($text);
        foreach (self::ANOMALY_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/[^\p{L}\p{N}\s.,;:!?\'-]/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function detectType(string $text): string
    {
        $lowerText = mb_strtolower($text);
        $scores = [];

        foreach (self::TYPE_KEYWORDS as $type => $keywords) {
            $scores[$type] = 0;
            foreach ($keywords as $keyword) {
                $scores[$type] += substr_count($lowerText, $keyword);
            }
        }

        arsort($scores);
        $bestType = array_key_first($scores);

        if (null !== $bestType && $scores[$bestType] > 0) {
            return ucfirst($bestType);
        }

        return 'Inconnu';
    }

    /**
     * @return string[]
     */
    private function extractKeywords(string $text): array
    {
        $stopWords = [
            'le', 'la', 'les', 'de', 'du', 'des', 'un', 'une', 'et', 'en', 'au', 'aux',
            'ce', 'ces', 'son', 'sa', 'ses', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes',
            'qui', 'que', 'quoi', 'dont', 'ou', 'pour', 'par', 'avec', 'dans', 'sur',
            'est', 'sont', 'a', 'ont', 'été', 'être', 'avoir', 'fait', 'faire',
            'pas', 'ne', 'plus', 'très', 'bien', 'aussi', 'comme', 'mais', 'donc',
        ];

        $words = preg_split('/[\s.,;:!?\'-]+/', mb_strtolower($text));
        if (false === $words) {
            return [];
        }

        $words = array_filter($words, static fn (string $w): bool => mb_strlen($w) > 3 && !\in_array($w, $stopWords, true));

        $frequency = array_count_values($words);
        arsort($frequency);

        return array_values(array_slice(array_keys($frequency), 0, 10));
    }

    private function generateSummary(string $text): string
    {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (false === $sentences || 0 === \count($sentences)) {
            return 'Aucun résumé disponible.';
        }

        $sentences = array_map('trim', $sentences);
        $sentences = array_filter($sentences, static fn (string $s): bool => mb_strlen($s) > 10);
        $summary = implode('. ', array_slice(array_values($sentences), 0, 3));

        if (mb_strlen($summary) > 200) {
            $summary = mb_substr($summary, 0, 197) . '...';
        }

        return $summary . '.';
    }
}
