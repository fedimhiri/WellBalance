<?php

namespace App\Controller\Admin;

use App\Entity\TypeRendezVous;
use App\Form\TypeRendezVousType;
use App\Repository\TypeRendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/medecin/type-rendez-vous', name: 'admin_type_rendez_vous_')]
#[IsGranted('ROLE_MEDECIN')]
class TypeRendezVousController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, TypeRendezVousRepository $repository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'nom');
        $dir = mb_strtolower((string) $request->query->get('dir', 'asc'));
        $dir = 'desc' === $dir ? 'desc' : 'asc';

        $types = $repository->searchList($q, $sort, $dir);

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return new JsonResponse([
                'items' => array_map(function (TypeRendezVous $type): array {
                    return [
                        'id' => $type->getId(),
                        'nomType' => (string) $type->getNomType(),
                        'description' => (string) ($type->getDescription() ?? '-'),
                        'showUrl' => null !== $type->getId()
                            ? $this->generateUrl('admin_type_rendez_vous_show', ['id' => $type->getId()])
                            : '#',
                        'editUrl' => null !== $type->getId()
                            ? $this->generateUrl('admin_type_rendez_vous_edit', ['id' => $type->getId()])
                            : '#',
                    ];
                }, $types),
            ]);
        }

        return $this->render('type_rendez_vous/index.html.twig', [
            'types' => $types,
            'currentSearch' => $q,
            'currentSort' => $sort,
            'currentDir' => $dir,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $typeRendezVous = new TypeRendezVous();
        $form = $this->createForm(TypeRendezVousType::class, $typeRendezVous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($typeRendezVous);
            $entityManager->flush();

            $this->addFlash('success', 'Le type de rendez-vous a ete cree avec succes.');

            return $this->redirectToRoute('admin_type_rendez_vous_index');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs.');
        }

        return $this->render('type_rendez_vous/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(TypeRendezVous $typeRendezVous): Response
    {
        return $this->render('type_rendez_vous/show.html.twig', [
            'type' => $typeRendezVous,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TypeRendezVous $typeRendezVous,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(TypeRendezVousType::class, $typeRendezVous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le type de rendez-vous a ete modifie avec succes.');

            return $this->redirectToRoute('admin_type_rendez_vous_show', ['id' => $typeRendezVous->getId()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs.');
        }

        return $this->render('type_rendez_vous/edit.html.twig', [
            'type' => $typeRendezVous,
            'form' => $form->createView(),
        ]);
    }
}
