<?php

namespace App\Controller\Admin\Nutrition;

use App\Entity\PlanNutrition;
use App\Form\PlanNutritionType;
use App\Repository\AlerteNutritionRepository;
use App\Repository\PlanNutritionRepository;
use App\Repository\SuiviNutritionRepository;
use App\Service\RiskAnalyzerService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/nutrition/plans')]
#[IsGranted('ROLE_NUTRITIONNISTE')]
class PlanNutritionController extends AbstractController
{
    #[Route('/', name: 'admin_plan_nutrition_index', methods: ['GET'])]
    public function index(Request $request, PlanNutritionRepository $repo): Response
    {
        $q = $request->query->getString('q', '');
        $objectif = $request->query->getString('objectif', '');
        $statut = $request->query->getString('statut', '');

        $q = $q !== '' ? $q : null;
        $objectif = $objectif !== '' ? $objectif : null;
        $statut = $statut !== '' ? $statut : null;

        $plans = $repo->searchAdmin($q, $objectif, $statut);

        // ✅ sécurité métier : nutritionniste ne voit que ses plans
        if (!$this->isGranted('ROLE_ADMIN')) {
            $me = $this->getUser();
            $plans = array_values(array_filter($plans, fn($p) => $p->getNutritionniste() === $me));
        }

        $total = count($plans);
        $actifs = 0;
        $today = new \DateTimeImmutable('today');

        foreach ($plans as $plan) {
            if ($plan->getDateFin() instanceof \DateTimeInterface && $plan->getDateFin() >= $today) {
                $actifs++;
            }
        }

        $stats = [
            'total' => $total,
            'actifs' => $actifs,
        ];

        $objectifs = $repo->getDistinctObjectifs();

        // ✅ AJAX live (recherche dynamique)
        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('admin/nutrition/plan_nutrition/_table.html.twig', [
                'plan_nutritions' => $plans,
            ]);

            return new JsonResponse([
                'html' => $html,
                'stats' => $stats,
            ]);
        }

        return $this->render('admin/nutrition/plan_nutrition/index.html.twig', [
            'plan_nutritions' => $plans,
            'stats' => $stats,
            'objectifs' => $objectifs,
            'filters' => [
                'q' => $q ?? '',
                'objectif' => $objectif ?? '',
                'statut' => $statut ?? '',
            ],
        ]);
    }

    #[Route('/stats', name: 'admin_plan_nutrition_stats', methods: ['GET'])]
    public function stats(PlanNutritionRepository $repo): Response
    {
        $global = $repo->getGlobalStats();
        $byObjectif = $repo->getStatsByObjectif();
        $perMonth = $repo->getPlansPerMonth(6);

        return $this->render('admin/nutrition/plan_nutrition/stats.html.twig', [
            'global' => $global,
            'byObjectif' => $byObjectif,
            'perMonth' => $perMonth,
        ]);
    }

    #[Route('/new', name: 'admin_plan_nutrition_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $planNutrition = new PlanNutrition();
        $form = $this->createForm(PlanNutritionType::class, $planNutrition);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if (method_exists($planNutrition, 'setNutritionniste')) {
                $planNutrition->setNutritionniste($this->getUser());
            }

            if (method_exists($planNutrition, 'calculerPeriode') && method_exists($planNutrition, 'setPeriode')) {
                $planNutrition->setPeriode($planNutrition->calculerPeriode());
            }

            $entityManager->persist($planNutrition);
            $entityManager->flush();

            $this->addFlash('success', 'Le plan nutrition a été créé avec succès.');
            return $this->redirectToRoute('admin_plan_nutrition_index');
        }

        return $this->render('admin/nutrition/plan_nutrition/new.html.twig', [
            'plan_nutrition' => $planNutrition,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_plan_nutrition_show', methods: ['GET'])]
    public function show(
        PlanNutrition $planNutrition,
        AlerteNutritionRepository $alerteRepo,
        SuiviNutritionRepository $suiviRepo
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($planNutrition->getNutritionniste() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
        }

        $stats = [];
        if (method_exists($planNutrition, 'getStats')) {
            $stats = $planNutrition->getStats();
        }

        $alertes = $alerteRepo->findBy(
            ['planNutrition' => $planNutrition, 'resolvedAt' => null],
            ['createdAt' => 'DESC']
        );

        $suivisCount = count($suiviRepo->findBy(['planNutrition' => $planNutrition]));

        return $this->render('admin/nutrition/plan_nutrition/show.html.twig', [
            'plan_nutrition' => $planNutrition,
            'stats' => $stats,
            'alertes' => $alertes,
            'suivis_count' => $suivisCount,
        ]);
    }

    #[Route('/{id}/analyze', name: 'admin_plan_nutrition_analyze', methods: ['GET'])]
    public function analyze(
        PlanNutrition $planNutrition,
        RiskAnalyzerService $riskAnalyzer
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($planNutrition->getNutritionniste() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
        }

        $risks = $riskAnalyzer->analyze($planNutrition, true);

        return $this->render('admin/nutrition/plan_nutrition/analyze.html.twig', [
            'plan_nutrition' => $planNutrition,
            'risks' => $risks,
        ]);
    }

    #[Route('/{id}/pdf', name: 'admin_plan_nutrition_pdf', methods: ['GET'])]
    public function exportPdf(PlanNutrition $planNutrition): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($planNutrition->getNutritionniste() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->renderView('pdf/plan_nutrition_admin.html.twig', [
            'plan' => $planNutrition,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'plan_nutrition_' . $planNutrition->getId() . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]
        );
    }

    #[Route('/{id}/edit', name: 'admin_plan_nutrition_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PlanNutrition $planNutrition, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($planNutrition->getNutritionniste() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
        }

        $form = $this->createForm(PlanNutritionType::class, $planNutrition);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if (method_exists($planNutrition, 'getNutritionniste') && method_exists($planNutrition, 'setNutritionniste')) {
                if ($planNutrition->getNutritionniste() === null) {
                    $planNutrition->setNutritionniste($this->getUser());
                }
            }

            if (method_exists($planNutrition, 'calculerPeriode') && method_exists($planNutrition, 'setPeriode')) {
                $planNutrition->setPeriode($planNutrition->calculerPeriode());
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le plan nutrition a été modifié avec succès.');
            return $this->redirectToRoute('admin_plan_nutrition_index');
        }

        return $this->render('admin/nutrition/plan_nutrition/edit.html.twig', [
            'plan_nutrition' => $planNutrition,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_plan_nutrition_delete', methods: ['POST'])]
    public function delete(Request $request, PlanNutrition $planNutrition, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            if ($planNutrition->getNutritionniste() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
        }

        if ($this->isCsrfTokenValid('delete' . $planNutrition->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($planNutrition);
            $entityManager->flush();
            $this->addFlash('success', 'Le plan nutrition a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_plan_nutrition_index');
    }
}
