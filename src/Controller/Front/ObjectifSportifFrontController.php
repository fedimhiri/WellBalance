<?php

namespace App\Controller\Front;

use App\Entity\ObjectifSportif;
use App\Repository\ObjectifSportifRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sport/objectifs')]
#[IsGranted('ROLE_USER')]
class ObjectifSportifFrontController extends AbstractController
{
    #[Route('/', name: 'front_objectif_sportif_index', methods: ['GET'])]
    public function index(ObjectifSportifRepository $repository): Response
    {
        // On récupère les objectifs liés à l'utilisateur connecté
        $user = $this->getUser();
        
        // Si la méthode findByUser n'existe pas encore, on peut utiliser findBy(['user' => $user])
        $objectifs = $repository->findBy(['user' => $user], ['id' => 'DESC']);

        return $this->render('frontend/sport/objectif_sportif/index.html.twig', [
            'objectifs' => $objectifs,
        ]);
    }

    #[Route('/{id}', name: 'front_objectif_sportif_show', methods: ['GET'])]
    public function show(ObjectifSportif $objectifSportif): Response
    {
        // Vérification d'accès
        if ($objectifSportif->getUser() !== $this->getUser()) {
             throw $this->createAccessDeniedException("Vous n'avez pas accès à cet objectif.");
        }

        return $this->render('frontend/sport/objectif_sportif/show.html.twig', [
            'objectif' => $objectifSportif,
        ]);
    }
}
