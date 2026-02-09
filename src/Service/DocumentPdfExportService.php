<?php

namespace App\Service;

use App\Entity\Document;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service to export document list to PDF using Dompdf.
 */
class DocumentPdfExportService
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * Generate PDF response with document list.
     *
     * @param Document[] $documents
     */
    public function export(array $documents): Response
    {
        $html = $this->buildHtml($documents);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="documents-' . date('Y-m-d-His') . '.pdf"',
            ]
        );
    }

    /**
     * @param Document[] $documents
     */
    private function buildHtml(array $documents): string
    {
        $rows = '';
        foreach ($documents as $doc) {
            $titre = htmlspecialchars((string) $doc->getTitre());
            $type = htmlspecialchars((string) $doc->getTypeDocument());
            $categorie = $doc->getCategorie() ? htmlspecialchars((string) $doc->getCategorie()->getDescription()) : '-';
            $date = $doc->getDateUpload() ? $doc->getDateUpload()->format('d/m/Y H:i') : '-';

            $rows .= "<tr><td>{$titre}</td><td>{$type}</td><td>{$categorie}</td><td>{$date}</td></tr>";
        }

        $exportDate = date('d/m/Y à H:i');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #696cff; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Liste des documents - WellBalance</h1>
    <p>Export généré le : {$exportDate}</p>
    <table>
        <thead>
            <tr>
                <th>Titre</th>
                <th>Type</th>
                <th>Catégorie</th>
                <th>Date d'upload</th>
            </tr>
        </thead>
        <tbody>{$rows}</tbody>
    </table>
</body>
</html>
HTML;
    }
}
