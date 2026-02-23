<?php

namespace App\Controller\Admin\Nutrition;

use App\Entity\Repas;
use App\Form\RepasType;
use App\Repository\PlanNutritionRepository;
use App\Repository\RepasRepository;
use App\Service\MealPresetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/nutrition/repas')]
#[IsGranted('ROLE_NUTRITIONNISTE')]
class RepasController extends AbstractController
{
    #[Route('/', name: 'admin_repas_index', methods: ['GET'])]
    public function index(RepasRepository $repo): Response
    {
        $repas = $repo->findAll();

        return $this->render('admin/nutrition/repas/index.html.twig', [
            'repas' => $repas,
        ]);
    }

    // ✅ URL: /admin/nutrition/repas/new?plan_id=2
    #[Route('/new', name: 'admin_repas_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        PlanNutritionRepository $planRepo,
        MealPresetService $presetService
    ): Response {
        $planId = $request->query->getInt('plan_id', 0);
        $plan = $planId ? $planRepo->find($planId) : null;

        if (!$plan) {
            throw $this->createNotFound_exception('Plan nutrition introuvable.');
        }

        // ✅ Sécurité métier : le nutritionniste ne gère que ses plans
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($plan->getNutritionniste() && $plan->getNutritionniste() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
        }

        $repas = new Repas();
        $repas->setPlanNutrition($plan);

        $form = $this->createForm(RepasType::class, $repas, [
            'plan' => $plan,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $repas->setPlanNutrition($plan);

            $em->persist($repas);
            $em->flush();

            $this->addFlash('success', 'Repas ajouté avec succès.');
            return $this->redirectToRoute('admin_plan_nutrition_show', ['id' => $plan->getId()]);
        }

        return $this->render('admin/nutrition/repas/new.html.twig', [
            'form' => $form->createView(),
            'plan' => $plan,
            // ✅ repas précis selon objectif
            'presets' => $presetService->presetsForObjectif($plan->getObjectif()),
        ]);
    }

    #[Route('/{id}', name: 'admin_repas_show', methods: ['GET'])]
    public function show(Repas $repa): Response
    {
        $plan = $repa->getPlanNutrition();
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($plan && $plan->getNutritionniste() && $plan->getNutritionniste() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
        }

        return $this->render('admin/nutrition/repas/show.html.twig', [
            'repa' => $repa,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_repas_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Repas $repa,
        EntityManagerInterface $em,
        MealPresetService $presetService
    ): Response {
        $plan = $repa->getPlanNutrition();

        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($plan && $plan->getNutritionniste() && $plan->getNutritionniste() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
        }

        $form = $this->createForm(RepasType::class, $repa, [
            'plan' => $plan,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($plan) {
                $repa->setPlanNutrition($plan);
            }

            $em->flush();
            $this->addFlash('success', 'Repas modifié avec succès.');

            return $this->redirectToRoute('admin_plan_nutrition_show', ['id' => $plan?->getId()]);
        }

        return $this->render('admin/nutrition/repas/edit.html.twig', [
            'form' => $form->createView(),
            'repa' => $repa,
            'presets' => $presetService->presetsForObjectif($plan?->getObjectif()),
        ]);
    }

    #[Route('/{id}', name: 'admin_repas_delete', methods: ['POST'])]
    public function delete(Request $request, Repas $repa, EntityManagerInterface $em): Response
    {
        $plan = $repa->getPlanNutrition();

        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($plan && $plan->getNutritionniste() && $plan->getNutritionniste() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
        }

        if ($this->isCsrfTokenValid('delete'.$repa->getId(), (string) $request->request->get('_token'))) {
            $em->remove($repa);
            $em->flush();
            $this->addFlash('success', 'Repas supprimé.');
        }

        return $this->redirectToRoute('admin_plan_nutrition_show', ['id' => $plan?->getId()]);
    }
}
