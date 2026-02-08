<?php

namespace App\Controller;

use App\Entity\ObjectifSportif;
use App\Form\ObjectifSportifType;
use App\Repository\ObjectifSportifRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/objectif/sportif')]
class ObjectifSportifController extends AbstractController
{
    #[Route('/', name: 'app_objectif_sportif_index', methods: ['GET'])]
    public function index(Request $request, ObjectifSportifRepository $objectifSportifRepository): Response
    {
        $query = $request->query->get('q');
        $sort = $request->query->get('sort', 'o.id');
        $direction = $request->query->get('direction', 'DESC');

        return $this->render('objectif_sportif/index.html.twig', [
            'objectif_sportifs' => $objectifSportifRepository->search($query, $sort, $direction),
            'query' => $query,
            'sort' => $sort,
            'direction' => $direction
        ]);
    }

    #[Route('/new', name: 'app_objectif_sportif_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ObjectifSportifRepository $objectifSportifRepository): Response
    {
        $objectifSportif = new ObjectifSportif();
        $form = $this->createForm(ObjectifSportifType::class, $objectifSportif);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $objectifSportifRepository->save($objectifSportif, true);
            $this->addFlash('success', 'Objectif créé avec succès !');

            return $this->redirectToRoute('app_objectif_sportif_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('objectif_sportif/new.html.twig', [
            'objectif_sportif' => $objectifSportif,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_objectif_sportif_show', methods: ['GET'])]
    public function show(ObjectifSportif $objectifSportif): Response
    {
        return $this->render('objectif_sportif/show.html.twig', [
            'objectif_sportif' => $objectifSportif,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_objectif_sportif_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ObjectifSportif $objectifSportif, ObjectifSportifRepository $objectifSportifRepository): Response
    {
        $form = $this->createForm(ObjectifSportifType::class, $objectifSportif);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $objectifSportifRepository->save($objectifSportif, true);
            $this->addFlash('success', 'Objectif mis à jour avec succès !');

            return $this->redirectToRoute('app_objectif_sportif_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('objectif_sportif/edit.html.twig', [
            'objectif_sportif' => $objectifSportif,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_objectif_sportif_delete', methods: ['POST'])]
    public function delete(Request $request, ObjectifSportif $objectifSportif, ObjectifSportifRepository $objectifSportifRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$objectifSportif->getId(), $request->request->get('_token'))) {
            $objectifSportifRepository->remove($objectifSportif, true);
            $this->addFlash('success', 'Objectif supprimé avec succès !');
        }

        return $this->redirectToRoute('app_objectif_sportif_index', [], Response::HTTP_SEE_OTHER);
    }
}
