<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Repository\RendezVousRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rendez/vous')]
#[IsGranted('ROLE_ADMIN')]
final class RendezVousController extends AbstractController
{
    #[Route('', name: 'app_rendez_vous_index', methods: ['GET'])]
    public function index(Request $request, RendezVousRepository $repo): Response
    {
        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'id');
        $dir  = $request->query->get('dir', 'ASC');

        $rendezVous = $repo->searchAndSort($search, $sort, $dir);

        return $this->render('backend/rendez_vous/index.html.twig', [
            'rendez_vouses' => $rendezVous,
            'search' => $search,
            'sort' => $sort,
            'dir' => strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC',
        ]);
    }

    #[Route('/voir/{id<\d+>}', name: 'app_rendez_vous_show', methods: ['GET'])]
    public function show(RendezVous $rendezVou): Response
    {
        return $this->render('backend/rendez_vous/show.html.twig', [
            'rendez_vou' => $rendezVou,
        ]);
    }
}