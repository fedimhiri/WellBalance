<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CategorieDocument;
use App\Form\CategorieDocumentType;
use App\Repository\CategorieDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categories-document', name: 'admin_categorie_document_')]
#[IsGranted('ROLE_ADMIN')]
class CategorieCrudController extends AbstractController
{
    public function __construct(
        private readonly CategorieDocumentRepository $categorieRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $categories = $this->categorieRepository->findAll();

        return $this->render('admin/document/categorie_index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $categorie = new CategorieDocument();
        $form = $this->createForm(CategorieDocumentType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($categorie);
            $this->entityManager->flush();

            $this->addFlash('success', 'Catégorie créée avec succès.');

            return $this->redirectToRoute('admin_categorie_document_index');
        }

        return $this->render('admin/document/categorie_form.html.twig', [
            'form' => $form,
            'categorie' => $categorie,
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, CategorieDocument $categorie): Response
    {
        $form = $this->createForm(CategorieDocumentType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Catégorie modifiée avec succès.');

            return $this->redirectToRoute('admin_categorie_document_index');
        }

        return $this->render('admin/document/categorie_form.html.twig', [
            'form' => $form,
            'categorie' => $categorie,
            'is_edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, CategorieDocument $categorie): Response
    {
        $token = $request->request->get('_token');
        if (!\is_string($token) || !$this->isCsrfTokenValid('delete_categorie_' . $categorie->getId(), $token)) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_categorie_document_index');
        }

        $this->entityManager->remove($categorie);
        $this->entityManager->flush();

        $this->addFlash('success', 'Catégorie supprimée.');

        return $this->redirectToRoute('admin_categorie_document_index');
    }
}
