<?php

namespace App\Controller;

use App\Entity\TypeRendezVous;
use App\Form\TypeRendezVous1Type;
use App\Repository\TypeRendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/type/rendez-vous')]
final class TypeRendezVousController extends AbstractController
{
    #[Route('', name: 'app_type_rendez_vous_index', methods: ['GET'])]
    public function index(Request $request, TypeRendezVousRepository $repo): Response
    {
        $q    = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'id');
        $dir  = strtolower((string) $request->query->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Champs autorisés pour éviter l'injection via orderBy
        $allowedSort = ['id', 'libelle', 'description'];
        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }

        $qb = $repo->createQueryBuilder('t');

        if ($q !== '') {
            $qb
                ->andWhere('LOWER(t.libelle) LIKE :q OR LOWER(COALESCE(t.description, \'\')) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        $qb->orderBy('t.' . $sort, $dir);

        return $this->render('backend/type_rendez_vous/index.html.twig', [
            'type_rendez_vouses' => $qb->getQuery()->getResult(),
            'q'    => $q,
            'sort' => $sort,
            'dir'  => $dir,
        ]);
    }

    #[Route('/new', name: 'app_type_rendez_vous_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $typeRendezVous = new TypeRendezVous();
        $form = $this->createForm(TypeRendezVous1Type::class, $typeRendezVous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($typeRendezVous);
            $em->flush();

            $this->addFlash('success', 'Type de rendez-vous créé avec succès.');
            return $this->redirectToRoute('app_type_rendez_vous_index');
        }

        return $this->render('backend/type_rendez_vous/new.html.twig', [
            'type_rendez_vous' => $typeRendezVous,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_type_rendez_vous_show', methods: ['GET'])]
    public function show(TypeRendezVous $typeRendezVous): Response
    {
        return $this->render('backend/type_rendez_vous/show.html.twig', [
            'type_rendez_vous' => $typeRendezVous,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_type_rendez_vous_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TypeRendezVous $typeRendezVous, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TypeRendezVous1Type::class, $typeRendezVous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Type de rendez-vous modifié avec succès.');
            return $this->redirectToRoute('app_type_rendez_vous_index');
        }

        return $this->render('backend/type_rendez_vous/edit.html.twig', [
            'type_rendez_vous' => $typeRendezVous,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_type_rendez_vous_delete', methods: ['POST'])]
    public function delete(Request $request, TypeRendezVous $typeRendezVous, EntityManagerInterface $em): Response
    {
        // CSRF: token basé sur l'id, côté twig: csrf_token('delete' ~ type_rendez_vous.id)
        if ($this->isCsrfTokenValid('delete' . $typeRendezVous->getId(), (string) $request->request->get('_token'))) {
            $em->remove($typeRendezVous);
            $em->flush();

            $this->addFlash('success', 'Type de rendez-vous supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_type_rendez_vous_index');
    }
}
