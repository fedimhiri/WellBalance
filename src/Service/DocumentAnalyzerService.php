<?php

namespace App\Service;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DocumentAnalyzerService
{
    private const OCR_SPACE_API_URL = 'https://api.ocr.space/parse/image';
    private const OCR_SPACE_API_KEY = 'K88068780488957';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private ParameterBagInterface $params // ✅ Changez ceci
    ) {}

    /**
     * Analyser automatiquement un document (PDF/Image)
     */
    public function analyzeDocument(string $filename): ?array
    {
        try {
            // ✅ Récupérer projectDir depuis les paramètres
            $projectDir = $this->params->get('kernel.project_dir');
            $filePath = $projectDir . '/public/uploads/messenger/' . $filename;
            
            if (!file_exists($filePath)) {
                $this->logger->warning("Fichier introuvable: $filename");
                return null;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $supportedFormats = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf'];

            if (!in_array($extension, $supportedFormats)) {
                return [
                    'success' => false,
                    'type' => 'unsupported',
                    'message' => 'Format non supporté pour l\'analyse IA'
                ];
            }

            // 🚀 Appel API OCR.space
            $response = $this->httpClient->request('POST', self::OCR_SPACE_API_URL, [
                'headers' => [
                    'apikey' => self::OCR_SPACE_API_KEY,
                ],
                'body' => [
                    'file' => fopen($filePath, 'r'),
                    'language' => 'fre',
                    'isOverlayRequired' => 'false',
                    'detectOrientation' => 'true',
                    'scale' => 'true',
                    'OCREngine' => '2',
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();

            if (isset($data['ParsedResults'][0]['ParsedText'])) {
                $extractedText = trim($data['ParsedResults'][0]['ParsedText']);
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'text' => $extractedText,
                    'summary' => $this->generateSummary($extractedText),
                    'analyzed_at' => (new \DateTime())->format('d/m/Y à H:i'),
                ];
            }

            return [
                'success' => false,
                'type' => 'api_error',
                'message' => $data['ErrorMessage'][0] ?? 'Erreur lors de l\'analyse OCR'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Erreur analyse IA: ' . $e->getMessage());
            
            return [
                'success' => false,
                'type' => 'exception',
                'message' => 'Erreur technique: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Générer un résumé intelligent du document
     */
    private function generateSummary(string $text): array
    {
        $text = trim($text);
        $words = str_word_count($text);
        $lines = count(array_filter(explode("\n", $text)));
        
        return [
            'document_type' => $this->detectDocumentType($text),
            'word_count' => $words,
            'line_count' => $lines,
            'key_information' => $this->extractKeyInfo($text),
            'preview' => mb_substr($text, 0, 300) . ($words > 60 ? '...' : ''),
            'is_empty' => empty($text) || $words < 3,
        ];
    }

    /**
     * Détecter le type de document médical
     */
    private function detectDocumentType(string $text): string
    {
        $lower = mb_strtolower($text);
        
        $patterns = [
            'Ordonnance' => '/ordonnance|prescription|médicament|posologie|traitement|rx/i',
            'Analyse Médicale' => '/analyse|résultat|laboratoire|biologie|hémato|glycémie|cholestérol/i',
            'Imagerie' => '/radiographie|scanner|irm|échographie|imagerie|radio|tomodensitométrie/i',
            'Certificat Médical' => '/attestation|certificat|arrêt.{0,10}travail|aptitude/i',
            'Compte Rendu' => '/compte.{0,5}rendu|consultation|examen|diagnostic|bilan/i',
            'Courrier Médical' => '/courrier|lettre|correspondance/i',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $lower)) {
                return $type;
            }
        }
        
        return 'Document Général';
    }

    /**
     * Extraire les informations clés
     */
    private function extractKeyInfo(string $text): array
    {
        $info = [];

        // Dates
        if (preg_match_all('/\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}/', $text, $matches)) {
            $info['dates'] = array_slice(array_unique($matches[0]), 0, 5);
        }

        // Médicaments (mots en majuscules)
        if (preg_match_all('/\b[A-Z]{3,}[A-Z\s]*\b/', $text, $matches)) {
            $meds = array_filter(array_unique($matches[0]), function($m) {
                return strlen($m) > 3 && strlen($m) < 30;
            });
            $info['medications'] = array_slice(array_values($meds), 0, 10);
        }

        // Valeurs avec unités médicales
        if (preg_match_all('/\d+[\.,]?\d*\s*(?:mg|g|ml|l|mmol|µg|UI|%|\/mm³|kg|cm)/i', $text, $matches)) {
            $info['values'] = array_slice(array_unique($matches[0]), 0, 10);
        }

        return $info;
    }
}