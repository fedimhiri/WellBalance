<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/documents/crud', name: 'admin_document_crud_')]
#[IsGranted('ROLE_ADMIN')]
class DocumentCrudController extends AbstractController
{
    public function __construct(
        private readonly DocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search');
        $sortField = $request->query->get('sort', 'dateUpload');
        $direction = $request->query->get('direction', 'DESC');

        $documents = $this->documentRepository->searchAndSort(
            \is_string($search) ? $search : null,
            \is_string($sortField) ? $sortField : 'dateUpload',
            \is_string($direction) ? $direction : 'DESC',
            null
        );

        return $this->render('admin/document/crud_index.html.twig', [
            'documents' => $documents,
            'search' => $search,
            'sort' => $sortField,
            'direction' => $direction,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Document $document): Response
    {
        return $this->render('admin/document/crud_show.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Document $document): Response
    {
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('admin_delete_document_' . $document->getId(), $token)) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_document_crud_index');
        }

        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $document->getCheminFichier();
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->entityManager->remove($document);
        $this->entityManager->flush();

        $this->addFlash('success', 'Document supprimé.');

        return $this->redirectToRoute('admin_document_crud_index');
    }
}
