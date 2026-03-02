<?php

namespace App\Controller\Front;

use App\Entity\PlanNutrition;
use App\Entity\Repas;
use App\Form\Front\RepasFrontType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class RepasFrontController extends AbstractController
{
    #[Route('/nutrition/plans/{id}/repas/new', name: 'front_repas_new', methods: ['GET','POST'])]
    public function new(PlanNutrition $planNutrition, Request $request, EntityManagerInterface $em): Response
    {
        if ($planNutrition->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $repas = new Repas();
        $repas->setPlanNutrition($planNutrition);

        $form = $this->createForm(RepasFrontType::class, $repas);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($repas);
            $em->flush();

            $this->addFlash('success', 'Repas ajouté avec succès.');
            return $this->redirectToRoute('front_plan_nutrition_show', ['id' => $planNutrition->getId()]);
        }

        return $this->render('frontend/nutrition/repas/new.html.twig', [
            'form' => $form,
            'plan' => $planNutrition,
        ]);
    }

    #[Route('/nutrition/repas/{id}/edit', name: 'front_repas_edit', methods: ['GET','POST'])]
    public function edit(Repas $repas, Request $request, EntityManagerInterface $em): Response
    {
        $plan = $repas->getPlanNutrition();
        if (!$plan || $plan->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(RepasFrontType::class, $repas);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Repas modifié avec succès.');
            return $this->redirectToRoute('front_plan_nutrition_show', ['id' => $plan->getId()]);
        }

        return $this->render('frontend/nutrition/repas/edit.html.twig', [
            'form' => $form,
            'plan' => $plan,
            'repas' => $repas,
        ]);
    }

    #[Route('/nutrition/repas/{id}/delete', name: 'front_repas_delete', methods: ['POST'])]
    public function delete(Repas $repas, Request $request, EntityManagerInterface $em): Response
    {
        $plan = $repas->getPlanNutrition();
        if (!$plan || $plan->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_repas'.$repas->getId(), (string) $request->request->get('_token'))) {
            $em->remove($repas);
            $em->flush();
            $this->addFlash('success', 'Repas supprimé.');
        }

        return $this->redirectToRoute('front_plan_nutrition_show', ['id' => $plan->getId()]);
    }
}
