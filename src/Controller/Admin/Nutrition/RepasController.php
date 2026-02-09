<?php

namespace App\Controller\Admin\Nutrition;

use App\Entity\Repas;
use App\Entity\PlanNutrition;
use App\Form\RepasType;
use App\Repository\RepasRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/nutrition/repas')]
#[IsGranted('ROLE_ADMIN')]
class RepasController extends AbstractController
{
    #[Route('/', name: 'admin_repas_index', methods: ['GET'])]
    public function index(RepasRepository $repasRepository, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 15;

        $type = $request->query->get('type');
        $date = $request->query->get('date'); // attendu YYYY-MM-DD
        $planId = $request->query->getInt('plan');

        $repas = $repasRepository->findWithFilters($type, $date, $planId, $page, $limit);
        $todayCalories = $repasRepository->getTodayTotalCalories();

        return $this->render('admin/nutrition/repas/index.html.twig', [
            'repas' => $repas,
            'today_calories' => $todayCalories,
            'types_repas' => ['Petit-déjeuner', 'Déjeuner', 'Dîner', 'Collation', 'En-cas'],
        ]);
    }

    #[Route('/new', name: 'admin_repas_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $repas = new Repas();

        // préselection plan si plan_id présent
        $planId = $request->query->getInt('plan_id');
        if ($planId) {
            $plan = $entityManager->getRepository(PlanNutrition::class)->find($planId);
            if ($plan) {
                $repas->setPlanNutrition($plan);
            }
        }

        // date par défaut
        if (!$repas->getDateRepas()) {
            $repas->setDateRepas(new \DateTime());
        }

        $form = $this->createForm(RepasType::class, $repas);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($repas);
            $entityManager->flush();

            $this->addFlash('success', 'Le repas a été créé avec succès.');

            // si on vient d’un plan → retour plan
            if ($planId) {
                return $this->redirectToRoute('admin_plan_nutrition_show', ['id' => $planId]);
            }

            return $this->redirectToRoute('admin_repas_index');
        }

        return $this->render('admin/nutrition/repas/new.html.twig', [
            'repa' => $repas,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_repas_show', methods: ['GET'])]
    public function show(Repas $repa): Response
    {
        return $this->render('admin/nutrition/repas/show.html.twig', [
            'repa' => $repa,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_repas_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Repas $repa, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RepasType::class, $repa);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Le repas a été modifié avec succès.');

            return $this->redirectToRoute('admin_repas_index');
        }

        return $this->render('admin/nutrition/repas/edit.html.twig', [
            'repa' => $repa,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_repas_delete', methods: ['POST'])]
    public function delete(Request $request, Repas $repa, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$repa->getId(), (string) $request->request->get('_token'))) {
            $planId = $repa->getPlanNutrition()?->getId();

            $entityManager->remove($repa);
            $entityManager->flush();

            $this->addFlash('success', 'Le repas a été supprimé avec succès.');

            if ($planId) {
                return $this->redirectToRoute('admin_plan_nutrition_show', ['id' => $planId]);
            }
        }

        return $this->redirectToRoute('admin_repas_index');
    }
}
