<?php

declare(strict_types=1);

namespace App\Controller\Front;

use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Service\DocumentUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents', name: 'front_document_')]
#[Route('/Vue/documents', name: 'vue_documents')]
#[IsGranted('ROLE_USER')]
class DocumentController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentUploadService $uploadService,
    ) {
    }

    #[Route('/', name: 'front_documents', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (null === $user) {
            throw $this->createAccessDeniedException();
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 15;

        $documents = $this->documentRepository->findPaginated($page, $limit, $user);
        $total = $this->documentRepository->countPaginated($user);
        $totalPages = (int) ceil($total / $limit);

        return $this->render('front/document/index.html.twig', [
            'documents' => $documents,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'front_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->getUser();
        if (null === $user) {
            throw $this->createAccessDeniedException();
        }

        $document = new Document();
        $form = $this->createForm(DocumentType::class, $document, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('fichier')->getData();
            if (null !== $file) {
                $document->setCheminFichier($this->uploadService->upload($file));
            }
            $document->setDateUpload(new \DateTimeImmutable());
            $document->setUser($user);

            $this->entityManager->persist($document);
            $this->entityManager->flush();

            $this->addFlash('success', 'Document ajouté avec succès.');

            return $this->redirectToRoute('front_documents');
        }

        return $this->render('front/document/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'front_document_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Document $document): Response
    {
        $this->denyUnlessOwned($document);

        return $this->render('front/document/show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/delete', name: 'front_document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Document $document): Response
    {
        $this->denyUnlessOwned($document);

        if (!$this->isCsrfTokenValid('delete_document_' . $document->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('front_documents');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $document->getCheminFichier();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->addFlash('success', 'Document supprimé.');

        return $this->redirectToRoute('front_documents');
    }

    #[Route('/{id}/download', name: 'front_document_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(Document $document): BinaryFileResponse
    {
        $this->denyUnlessOwned($document);

        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $document->getCheminFichier();
        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($document->getCheminFichier()));

        return $response;
    }

    private function denyUnlessOwned(Document $document): void
    {
        $user = $this->getUser();
        if (null === $user || $document->getUser() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce document.');
        }
    }
}
