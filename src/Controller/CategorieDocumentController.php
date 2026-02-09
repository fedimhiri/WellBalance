<?php

namespace App\Controller;

use App\Entity\CategorieDocument;
use App\Form\CategorieDocumentType;
use App\Repository\CategorieDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/categorie-document')]
#[IsGranted('ROLE_ADMIN')]
final class CategorieDocumentController extends AbstractController
{
    #[Route('', name: 'app_categorie_document_index', methods: ['GET'])]
    public function index(Request $request, CategorieDocumentRepository $repo): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'id');
        $dir = strtoupper((string) $request->query->get('dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $categories = $repo->searchAndSort($search ?: null, $sort, $dir);

        return $this->render('backend/categorie_document/index.html.twig', [
            'categorie_documents' => $categories,
            'q' => $search,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    #[Route('/new', name: 'app_categorie_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $categorie = new CategorieDocument();
        $form = $this->createForm(CategorieDocumentType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($categorie);
            $em->flush();
            $this->addFlash('success', 'Catégorie créée avec succès.');
            return $this->redirectToRoute('app_categorie_document_index');
        }

        return $this->render('backend/categorie_document/new.html.twig', [
            'categorie_document' => $categorie,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_categorie_document_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, CategorieDocument $categorieDocument, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategorieDocumentType::class, $categorieDocument);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Catégorie modifiée avec succès.');
            return $this->redirectToRoute('app_categorie_document_index');
        }

        return $this->render('backend/categorie_document/edit.html.twig', [
            'categorie_document' => $categorieDocument,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_categorie_document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, CategorieDocument $categorieDocument, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $categorieDocument->getId(), (string) $request->request->get('_token'))) {
            $em->remove($categorieDocument);
            $em->flush();
            $this->addFlash('success', 'Catégorie supprimée avec succès.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide.');
        }
        return $this->redirectToRoute('app_categorie_document_index');
    }
}
