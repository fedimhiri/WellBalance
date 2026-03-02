<?php

namespace App\Controller\Front;

use App\Entity\PlanNutrition;
use App\Entity\SuiviNutrition;
use App\Form\SuiviNutritionType;
use App\Repository\AlerteNutritionRepository;
use App\Repository\SuiviNutritionRepository;
use App\Service\AlerteNutritionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/nutrition/suivis')]
#[IsGranted('ROLE_USER')]
class SuiviNutritionFrontController extends AbstractController
{
    #[Route('/plan/{id}', name: 'front_suivi_index', methods: ['GET'])]
    public function index(
        PlanNutrition $plan,
        SuiviNutritionRepository $repo,
        AlerteNutritionRepository $alerteRepo
    ): Response {
        if ($plan->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // ✅ alertes ouvertes du plan uniquement
        $alertesPlan = $alerteRepo->findBy(
            ['planNutrition' => $plan, 'patient' => $this->getUser(), 'resolvedAt' => null],
            ['createdAt' => 'DESC']
        );

        return $this->render('frontend/nutrition/suivi/index.html.twig', [
            'plan' => $plan,
            'suivis' => $repo->findForPatientPlan($this->getUser(), $plan),
            'alertes' => $alertesPlan,
        ]);
    }

    #[Route('/new/{id}', name: 'front_suivi_new', methods: ['GET','POST'])]
    public function new(
        PlanNutrition $plan,
        Request $request,
        EntityManagerInterface $em,
        AlerteNutritionService $alerteService
    ): Response {
        if ($plan->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $suivi = new SuiviNutrition();
        $suivi->setPlanNutrition($plan);
        $suivi->setPatient($this->getUser());

        if (method_exists($suivi, 'setDateSuivi')) {
            $suivi->setDateSuivi(new \DateTime());
        }

        $form = $this->createForm(SuiviNutritionType::class, $suivi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if (method_exists($suivi, 'getDateSuivi') && method_exists($suivi, 'setDateSuivi')) {
                if (!$suivi->getDateSuivi()) {
                    $suivi->setDateSuivi(new \DateTime());
                }
            }

            $em->persist($suivi);
            $em->flush();

            // ✅ IA: recalcul + persistance alertes
            $alerteService->regenerateForPlan($plan);

            $this->addFlash('success', 'Suivi ajouté.');
            return $this->redirectToRoute('front_suivi_index', ['id' => $plan->getId()]);
        }

        return $this->render('frontend/nutrition/suivi/new.html.twig', [
            'plan' => $plan,
            'form' => $form->createView(),
        ]);
    }
}
