<?php

namespace App\Controller;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Service\DocumentPdfExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/document')]
#[IsGranted('ROLE_ADMIN')]
final class DocumentController extends AbstractController
{
    private const UPLOAD_DIR = 'uploads/documents';

    public function __construct(
        private string $projectDir,
        private DocumentPdfExportService $pdfExportService,
    ) {
    }

    #[Route('', name: 'app_document_index', methods: ['GET'])]
    public function index(Request $request, DocumentRepository $repo): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'dateUpload');
        $dir = strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $documents = $repo->searchAndSort($search ?: null, $sort, $dir, null);

        return $this->render('backend/document/index.html.twig', [
            'documents' => $documents,
            'q' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    #[Route('/export/pdf', name: 'app_document_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, DocumentRepository $repo): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'dateUpload');
        $dir = strtoupper((string) $request->query->get('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $documents = $repo->searchAndSort($search ?: null, $sort, $dir, null);

        return $this->pdfExportService->export($documents);
    }

    #[Route('/new', name: 'app_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $document = new Document();
        $document->setUser($this->getUser());
        $form = $this->createForm(DocumentType::class, $document, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('fichier')->getData();
            if ($file) {
                $filename = $this->uploadFile($file);
                if ($filename !== null) {
                    $document->setCheminFichier($filename);
                    $em->persist($document);
                    $em->flush();
                    $this->addFlash('success', 'Document créé avec succès.');
                    return $this->redirectToRoute('app_document_index');
                }
            }
            $this->addFlash('danger', 'Veuillez sélectionner un fichier valide (PDF, PNG ou JPEG, max 5 Mo).');
        }

        return $this->render('backend/document/new.html.twig', [
            'document' => $document,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_document_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Document $document): Response
    {
        return $this->render('backend/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_document_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DocumentType::class, $document, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('fichier')->getData();
            if ($file) {
                $filename = $this->uploadFile($file);
                if ($filename !== null) {
                    $document->setCheminFichier($filename);
                }
            }
            $em->flush();
            $this->addFlash('success', 'Document modifié avec succès.');
            return $this->redirectToRoute('app_document_index');
        }

        return $this->render('backend/document/edit.html.twig', [
            'document' => $document,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $document->getId(), (string) $request->request->get('_token'))) {
            $em->remove($document);
            $em->flush();
            $this->addFlash('success', 'Document supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide.');
        }
        return $this->redirectToRoute('app_document_index');
    }

    /**
     * Upload file to public/uploads/documents. Returns only the filename.
     */
    private function uploadFile(\Symfony\Component\HttpFoundation\File\UploadedFile $file): ?string
    {
        $ext = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $fileName = uniqid('doc_', true) . '.' . $ext;

        $uploadDir = $this->projectDir . '/public/' . self::UPLOAD_DIR;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        try {
            $file->move($uploadDir, $fileName);
            return $fileName;
        } catch (FileException) {
            return null;
        }
    }
}
