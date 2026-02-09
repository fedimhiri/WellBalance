<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Form\RendezVous1Type;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mon-rendez-vous')]
final class FrontRendezVousController extends AbstractController
{
    #[Route('', name: 'app_front_rendez_vous_index', methods: ['GET'])]
    public function index(Request $request, RendezVousRepository $repo): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'dateRdv');
        $dir = $request->query->get('dir', 'DESC');

        $rendezVous = $repo->searchAndSort($search, $sort, $dir);

        return $this->render('frontend/rendez_vous/index.html.twig', [
            'rendez_vouses' => $rendezVous,
            'search' => $search,
            'sort' => $sort,
            'dir' => strtoupper($dir),
        ]);
    }

    #[Route('/nouveau', name: 'app_front_rendez_vous_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $rendezVou = new RendezVous();
        $form = $this->createForm(RendezVous1Type::class, $rendezVou);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($rendezVou);
            $em->flush();

            $this->addFlash('success', 'Votre rendez-vous a été enregistré.');
            return $this->redirectToRoute('app_front_rendez_vous_index');
        }

        return $this->render('frontend/rendez_vous/new.html.twig', [
            'rendez_vou' => $rendezVou,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_front_rendez_vous_show', methods: ['GET'])]
    public function show(RendezVous $rendezVou): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('frontend/rendez_vous/show.html.twig', [
            'rendez_vou' => $rendezVou,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_front_rendez_vous_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, RendezVous $rendezVou, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(RendezVous1Type::class, $rendezVou);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Rendez-vous modifié avec succès.');
            return $this->redirectToRoute('app_front_rendez_vous_index');
        }

        return $this->render('frontend/rendez_vous/edit.html.twig', [
            'rendez_vou' => $rendezVou,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_front_rendez_vous_delete', methods: ['POST'])]
    public function delete(Request $request, RendezVous $rendezVou, EntityManagerInterface $em): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        if ($this->isCsrfTokenValid('delete'.$rendezVou->getId(), $request->request->get('_token'))) {
            $em->remove($rendezVou);
            $em->flush();
            $this->addFlash('success', 'Rendez-vous supprimé.');
        }

        return $this->redirectToRoute('app_front_rendez_vous_index');
    }
}