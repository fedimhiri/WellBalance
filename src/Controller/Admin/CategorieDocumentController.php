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

#[Route('/admin/categorie')]
#[IsGranted('ROLE_ADMIN')]
class CategorieDocumentController extends AbstractController
{
    public function __construct(
        private readonly CategorieDocumentRepository $categorieRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'admin_categorie_index', methods: ['GET'])]
    public function index(): Response
    {
        $categories = $this->categorieRepository->findBy([], ['nom' => 'ASC']);

        return $this->render('admin/categorie/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/new', name: 'admin_categorie_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $categorie = new CategorieDocument();
        $form = $this->createForm(CategorieDocumentType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($categorie);
            $this->entityManager->flush();

            $this->addFlash('success', 'Catégorie créée avec succès.');

            return $this->redirectToRoute('admin_categorie_index');
        }

        return $this->render('admin/categorie/new.html.twig', [
            'form' => $form,
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_categorie_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, CategorieDocument $categorie): Response
    {
        $form = $this->createForm(CategorieDocumentType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Catégorie mise à jour.');

            return $this->redirectToRoute('admin_categorie_index');
        }

        return $this->render('admin/categorie/edit.html.twig', [
            'form' => $form,
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_categorie_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, CategorieDocument $categorie): Response
    {
        if (!$this->isCsrfTokenValid('delete_categorie_' . $categorie->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_categorie_index');
        }

        $this->entityManager->remove($categorie);
        $this->entityManager->flush();

        $this->addFlash('success', 'Catégorie supprimée.');

        return $this->redirectToRoute('admin_categorie_index');
    }
}
