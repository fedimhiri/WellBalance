<?php

namespace App\Controller\Front;

use App\Entity\PlanNutrition;
use App\Repository\PlanNutritionRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/nutrition/plans')]
#[IsGranted('ROLE_USER')]
final class PlanNutritionFrontController extends AbstractController
{
    #[Route('/', name: 'front_plan_nutrition_index', methods: ['GET'])]
    public function index(Request $request, PlanNutritionRepository $repo): Response
    {
        $q = $request->query->getString('q', '');
        $objectif = $request->query->getString('objectif', '');
        $statut = $request->query->getString('statut', '');

        $q = $q !== '' ? $q : null;
        $objectif = $objectif !== '' ? $objectif : null;
        $statut = $statut !== '' ? $statut : null;

        $plans = $repo->searchForUser($this->getUser(), $q, $objectif, $statut);

        $objectifs = [];
        foreach ($plans as $p) {
            $obj = $p->getObjectif();
            if (is_string($obj) && $obj !== '') {
                $objectifs[$obj] = $obj;
            }
        }
        $objectifs = array_values($objectifs);

        // ✅ AJAX live
        if ($request->isXmlHttpRequest()) {
            $html = $this->renderView('frontend/nutrition/plans/_cards.html.twig', [
                'plans' => $plans,
            ]);

            return new JsonResponse([
                'html' => $html,
                'count' => count($plans),
            ]);
        }

        return $this->render('frontend/nutrition/plans/index.html.twig', [
            'plans' => $plans,
            'objectifs' => $objectifs,
            'filters' => [
                'q' => $q ?? '',
                'objectif' => $objectif ?? '',
                'statut' => $statut ?? '',
            ],
        ]);
    }

    #[Route('/{id}', name: 'front_plan_nutrition_show', methods: ['GET'])]
public function show(
    PlanNutrition $planNutrition,
    \App\Repository\AlerteNutritionRepository $alerteRepo,
    \App\Repository\SuiviNutritionRepository $suiviRepo
): Response {
    if ($planNutrition->getUser() !== $this->getUser()) {
        throw $this->createAccessDeniedException();
    }

    // ✅ Alertes ouvertes liées au plan (sans filtre patient pour éviter "vide" si patient non rempli)
    $alertes = $alerteRepo->findBy(
        ['planNutrition' => $planNutrition, 'resolvedAt' => null],
        ['createdAt' => 'DESC']
    );

    // ✅ Suivis du patient pour ce plan
    $suivis = $suiviRepo->findBy(
        ['planNutrition' => $planNutrition, 'patient' => $this->getUser()],
        ['dateSuivi' => 'DESC']
    );

    return $this->render('frontend/nutrition/plans/show.html.twig', [
        'plan' => $planNutrition,
        'alertes' => $alertes,
        'suivis' => $suivis,
    ]);
}

    #[Route('/{id}/pdf', name: 'front_plan_nutrition_pdf', methods: ['GET'])]
    public function exportPdf(PlanNutrition $planNutrition): Response
    {
        if ($planNutrition->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $html = $this->renderView('pdf/plan_nutrition_front.html.twig', [
            'plan' => $planNutrition,
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'mon_plan_nutrition_'.$planNutrition->getId().'.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]
        );
    }
}
