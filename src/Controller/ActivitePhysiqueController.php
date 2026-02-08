<?php

namespace App\Controller;

use App\Entity\ActivitePhysique;
use App\Form\ActivitePhysiqueType;
use App\Repository\ActivitePhysiqueRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/activite/physique')]
class ActivitePhysiqueController extends AbstractController
{
    #[Route('/', name: 'app_activite_physique_index', methods: ['GET'])]
    public function index(Request $request, ActivitePhysiqueRepository $activitePhysiqueRepository): Response
    {
        $query = $request->query->get('q');
        $sort = $request->query->get('sort', 'a.id');
        $direction = $request->query->get('direction', 'DESC');

        return $this->render('activite_physique/index.html.twig', [
            'activite_physiques' => $activitePhysiqueRepository->search($query, $sort, $direction),
            'query' => $query,
            'sort' => $sort,
            'direction' => $direction
        ]);
    }

    #[Route('/new', name: 'app_activite_physique_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ActivitePhysiqueRepository $activitePhysiqueRepository): Response
    {
        $activitePhysique = new ActivitePhysique();
        $form = $this->createForm(ActivitePhysiqueType::class, $activitePhysique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $activitePhysiqueRepository->save($activitePhysique, true);

            return $this->redirectToRoute('app_activite_physique_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('activite_physique/new.html.twig', [
            'activite_physique' => $activitePhysique,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_activite_physique_show', methods: ['GET'])]
    public function show(ActivitePhysique $activitePhysique): Response
    {
        return $this->render('activite_physique/show.html.twig', [
            'activite_physique' => $activitePhysique,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_activite_physique_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ActivitePhysique $activitePhysique, ActivitePhysiqueRepository $activitePhysiqueRepository): Response
    {
        $form = $this->createForm(ActivitePhysiqueType::class, $activitePhysique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $activitePhysiqueRepository->save($activitePhysique, true);
            $this->addFlash('success', 'Activité mise à jour avec succès !');

            return $this->redirectToRoute('app_activite_physique_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('activite_physique/edit.html.twig', [
            'activite_physique' => $activitePhysique,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_activite_physique_delete', methods: ['POST'])]
    public function delete(Request $request, ActivitePhysique $activitePhysique, ActivitePhysiqueRepository $activitePhysiqueRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$activitePhysique->getId(), $request->request->get('_token'))) {
            $activitePhysiqueRepository->remove($activitePhysique, true);
            $this->addFlash('success', 'Activité supprimée avec succès !');
        }

        return $this->redirectToRoute('app_activite_physique_index', [], Response::HTTP_SEE_OTHER);
    }
}
