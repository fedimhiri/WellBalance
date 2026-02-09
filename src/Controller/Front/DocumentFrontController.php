<?php

namespace App\Controller\Front;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\CategorieDocumentRepository;
use App\Repository\DocumentRepository;
use App\Service\DocumentPdfExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mes-documents')]
#[IsGranted('ROLE_USER')]
final class DocumentFrontController extends AbstractController
{
    private const UPLOAD_DIR = 'uploads/documents';

    private const SORT_FIELDS = [
        'dateUpload' => 'dateUpload',
        'titre' => 'titre',
        'typeDocument' => 'typeDocument',
    ];

    public function __construct(
        private readonly string $projectDir,
        private readonly DocumentPdfExportService $pdfExportService
    ) {}

    // ======================================================
    // INDEX + SEARCH + FILTER + SORT
    // ======================================================
    #[Route('', name: 'app_document_front_index', methods: ['GET'])]
    public function index(
        Request $request,
        DocumentRepository $repo,
        CategorieDocumentRepository $categorieRepo
    ): Response {
        $user = $this->getUser();

        $search = trim((string) $request->query->get('q'));
        $categorieId = $request->query->get('categorie');
        $type = $request->query->get('type');

        $sortKey = (string) $request->query->get('sort', 'dateUpload');
        $sort = self::SORT_FIELDS[$sortKey] ?? 'dateUpload';

        $dir = strtoupper((string) $request->query->get('dir', 'DESC'));
        $dir = $dir === 'ASC' ? 'ASC' : 'DESC';

        $documents = $repo->searchAdvanced(
            user: $user,
            search: $search ?: null,
            categorieId: $categorieId ? (int) $categorieId : null,
            type: $type ?: null,
            sort: $sort,
            dir: $dir
        );

        return $this->render('frontend/document/index.html.twig', [
            'documents' => $documents,
            'categories' => $categorieRepo->findAll(),
            'q' => $search,
            'categorie' => $categorieId,
            'type' => $type,
            'sort' => $sortKey,
            'dir' => $dir,
        ]);
    }

    // ======================================================
    // EXPORT PDF
    // ======================================================
    #[Route('/export/pdf', name: 'app_document_front_export_pdf', methods: ['GET'])]
    public function exportPdf(DocumentRepository $repo): Response
    {
        $documents = $repo->findBy(['user' => $this->getUser()]);

        return $this->pdfExportService->export($documents);
    }

    // ======================================================
    // CREATE
    // ======================================================
    #[Route('/new', name: 'app_document_front_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $document = new Document();
        $document->setUser($this->getUser());

        $form = $this->createForm(DocumentType::class, $document, [
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('fichier')->getData();

            if ($file && ($filename = $this->uploadFile($file))) {
                $document->setCheminFichier($filename);

                $em->persist($document);
                $em->flush();

                $this->addFlash('success', 'Document ajouté avec succès.');
                return $this->redirectToRoute('app_document_front_index');
            }

            $this->addFlash('danger', 'Erreur lors du téléchargement du fichier.');
        }

        return $this->render('frontend/document/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // ======================================================
    // SHOW
    // ======================================================
    #[Route('/{id}', name: 'app_document_front_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Document $document): Response
    {
        $this->denyAccessUnlessGranted('DOCUMENT_OWNER', $document);

        return $this->render('frontend/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    // ======================================================
    // EDIT
    // ======================================================
    #[Route('/{id}/edit', name: 'app_document_front_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Document $document,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('DOCUMENT_OWNER', $document);

        $form = $this->createForm(DocumentType::class, $document, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('fichier')->getData();

            if ($file && ($filename = $this->uploadFile($file))) {
                $document->setCheminFichier($filename);
            }

            $em->flush();
            $this->addFlash('success', 'Document mis à jour.');
            return $this->redirectToRoute('app_document_front_index');
        }

        return $this->render('frontend/document/edit.html.twig', [
            'form' => $form->createView(),
            'document' => $document,
        ]);
    }

    // ======================================================
    // DELETE
    // ======================================================
    #[Route('/{id}', name: 'app_document_front_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Document $document,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('DOCUMENT_OWNER', $document);

        if ($this->isCsrfTokenValid(
            'delete' . $document->getId(),
            (string) $request->request->get('_token')
        )) {
            $em->remove($document);
            $em->flush();

            $this->addFlash('success', 'Document supprimé.');
        }

        return $this->redirectToRoute('app_document_front_index');
    }

    // ======================================================
    // DOWNLOAD
    // ======================================================
    #[Route('/download/{id}', name: 'app_document_front_download', methods: ['GET'])]
    public function download(Document $document): Response
    {
        $this->denyAccessUnlessGranted('DOCUMENT_OWNER', $document);

        $path = $this->projectDir . '/public/' . self::UPLOAD_DIR . '/' . $document->getCheminFichier();

        return $this->file($path);
    }

    // ======================================================
    // FILE UPLOAD HELPER
    // ======================================================
    private function uploadFile(
        \Symfony\Component\HttpFoundation\File\UploadedFile $file
    ): ?string {
        $fileName = uniqid('doc_', true) . '.' . ($file->guessExtension() ?? 'bin');
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