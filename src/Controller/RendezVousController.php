<?php

namespace App\Controller;

use App\Entity\RendezVous;
use App\Entity\User;
use App\Form\RendezVousDemandeType;
use App\Repository\RendezVousRepository;
use App\Repository\TypeRendezVousRepository;
use App\Service\RendezVousConflictManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rdv', name: 'rendez_vous_')]
class RendezVousController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_MEDECIN')) {
            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        return $this->redirectToRoute('rendez_vous_new');
    }

    #[Route('/mes-demandes', name: 'suivi', methods: ['GET'])]
    public function suivi(
        Request $request,
        RendezVousRepository $repository,
        TypeRendezVousRepository $typeRepository
    ): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'date');
        $dir = mb_strtolower((string) $request->query->get('dir', 'asc'));
        $dir = 'desc' === $dir ? 'desc' : 'asc';
        $statutRaw = mb_strtoupper(trim((string) $request->query->get('statut', '')));
        $statut = in_array($statutRaw, RendezVous::STATUTS, true) ? $statutRaw : null;
        $typeRaw = trim((string) $request->query->get('type', ''));
        $typeId = ctype_digit($typeRaw) && (int) $typeRaw > 0 ? (int) $typeRaw : null;

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && in_array('ROLE_MEDECIN', $currentUser->getRoles(), true)) {
            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        if (
            !$currentUser instanceof User
            || !in_array('ROLE_PATIENT', $currentUser->getRoles(), true)
        ) {
            return $this->render('frontend/rendez_vous/suivi.html.twig', [
                'rendezVousList' => [],
                'types' => $typeRepository->findBy([], ['nomType' => 'ASC']),
                'accessError' => 'Connexion utilisateur (patient) requise pour voir le suivi de vos demandes.',
                'currentSearch' => $search,
                'currentSort' => $sort,
                'currentDir' => $dir,
                'currentStatut' => $statut,
                'currentType' => $typeId,
            ], new Response(status: Response::HTTP_FORBIDDEN));
        }

        $rendezVousList = $repository->searchForPatientList(
            $currentUser,
            $search,
            $sort,
            $dir,
            $statut,
            $typeId
        );

        if ($this->expectsJson($request)) {
            return $this->json([
                'items' => array_map(static function (RendezVous $rdv): array {
                    return [
                        'id' => $rdv->getId(),
                        'titre' => (string) ($rdv->getTitre() ?? ''),
                        'date' => $rdv->getDateRdv()?->format('d/m/Y') ?? '—',
                        'heure' => $rdv->getHeureRdv()?->format('H:i') ?? '—',
                        'medecin' => $rdv->getMedecin()?->getDisplayName() ?? '—',
                        'statut' => $rdv->getStatut(),
                        'statutBadgeClass' => match ($rdv->getStatut()) {
                            RendezVous::STATUT_EN_COURS => 'bg-warning text-dark',
                            RendezVous::STATUT_ACCEPTE => 'bg-success',
                            RendezVous::STATUT_REFUSE => 'bg-danger',
                            default => 'bg-secondary',
                        },
                    ];
                }, $rendezVousList),
            ]);
        }

        return $this->render('frontend/rendez_vous/suivi.html.twig', [
            'rendezVousList' => $rendezVousList,
            'types' => $typeRepository->findBy([], ['nomType' => 'ASC']),
            'accessError' => null,
            'currentSearch' => $search,
            'currentSort' => $sort,
            'currentDir' => $dir,
            'currentStatut' => $statut,
            'currentType' => $typeId,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        RendezVousConflictManager $conflictManager
    ): Response
    {
        $currentUser = $this->getUser();
        if ($currentUser instanceof User && in_array('ROLE_MEDECIN', $currentUser->getRoles(), true)) {
            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        $rdv = new RendezVous();
        $rdv->setStatut(RendezVous::STATUT_EN_COURS);
        $responseStatus = Response::HTTP_OK;

        $form = $this->createForm(RendezVousDemandeType::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $responseStatus = Response::HTTP_UNPROCESSABLE_ENTITY;

            if ($this->expectsJson($request)) {
                return $this->jsonValidationError(
                    'Le formulaire contient des erreurs.',
                    $this->extractFormErrors($form),
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $rdv->setStatut(RendezVous::STATUT_EN_COURS);

            if (
                !$currentUser instanceof User
                || !in_array('ROLE_PATIENT', $currentUser->getRoles(), true)
            ) {
                $form->addError(new FormError('Vous devez etre connecte en tant qu\'utilisateur (patient) pour reserver.'));
                $this->addFlash('error', 'Connexion utilisateur (patient) requise.');
                $responseStatus = Response::HTTP_FORBIDDEN;

                if ($this->expectsJson($request)) {
                    return $this->jsonValidationError(
                        'Compte utilisateur (patient) requis',
                        ['patient' => 'Vous devez etre connecte en tant qu\'utilisateur (patient) pour reserver.'],
                        Response::HTTP_FORBIDDEN
                    );
                }
            } else {
                $rdv->setPatient($currentUser);
            }

            $analysis = $conflictManager->analyze($rdv);

            if ($analysis['isPast']) {
                $form->get('heureRdv')->addError(new FormError('La date et l\'heure du rendez-vous ne peuvent pas etre dans le passe.'));
                $this->addFlash('error', 'Le formulaire contient des erreurs.');
                $responseStatus = Response::HTTP_UNPROCESSABLE_ENTITY;

                if ($this->expectsJson($request)) {
                    return $this->jsonValidationError(
                        'Date/heure invalide',
                        ['heure' => 'La date et l\'heure du rendez-vous ne peuvent pas etre dans le passe.'],
                        Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }
            } elseif ($analysis['hasConflict']) {
                $form->get('heureRdv')->addError(new FormError('Creneau deja reserve'));
                $this->addFlash('error', 'Creneau indisponible.');
                $responseStatus = Response::HTTP_CONFLICT;

                if ($this->expectsJson($request)) {
                    return $this->jsonConflictResponse($analysis['suggestions']);
                }
            }

            if ($form->isValid()) {
                try {
                    $entityManager->getConnection()->transactional(function () use ($entityManager, $rdv): void {
                        $entityManager->persist($rdv);
                        $entityManager->flush();
                    });

                    if ($this->expectsJson($request)) {
                        return $this->json([
                            'success' => true,
                            'message' => 'Rendez-vous cree avec succes.',
                            'data' => [
                                'id' => $rdv->getId(),
                                'doctorId' => $rdv->getMedecin()?->getId(),
                                'date' => $rdv->getDateRdv()?->format('Y-m-d'),
                                'time' => $rdv->getHeureRdv()?->format('H:i'),
                                'status' => $rdv->getStatut(),
                            ],
                        ], Response::HTTP_CREATED);
                    }

                    $this->addFlash('success', 'Votre demande de rendez-vous a ete enregistree.');

                    return $this->redirectToRoute('rendez_vous_new');
                } catch (UniqueConstraintViolationException) {
                    $suggestions = [];
                    $medecin = $rdv->getMedecin();
                    if (null !== $medecin && null !== $medecin->getId() && null !== $rdv->getDateRdv() && null !== $rdv->getHeureRdv()) {
                        $suggestions = $conflictManager->analyze($rdv)['suggestions'];
                    }

                    $form->get('heureRdv')->addError(new FormError('Creneau deja reserve'));
                    $this->addFlash('error', 'Creneau indisponible.');
                    $responseStatus = Response::HTTP_CONFLICT;

                    if ($this->expectsJson($request)) {
                        return $this->jsonConflictResponse($suggestions);
                    }
                }
            }
        }

        return $this->render('frontend/rendez_vous/new.html.twig', [
            'form' => $form->createView(),
        ], new Response(status: $responseStatus));
    }

    private function expectsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest() || 'json' === $request->getRequestFormat() || str_contains((string) $request->headers->get('Accept'), 'application/json');
    }

    /**
     * @param string[] $suggestions
     */
    private function jsonConflictResponse(array $suggestions): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => 'Creneau indisponible',
            'errors' => [
                'heure' => 'Creneau deja reserve',
            ],
            'meta' => [
                'suggestions' => $suggestions,
            ],
        ], Response::HTTP_CONFLICT);
    }

    /**
     * @param array<string, string> $errors
     */
    private function jsonValidationError(string $message, array $errors, int $statusCode): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    /**
     * @return array<string, string>
     */
    private function extractFormErrors(FormInterface $form): array
    {
        $errors = [];

        foreach ($form->all() as $child) {
            if (!$child->isSubmitted() || $child->isValid()) {
                continue;
            }

            foreach ($child->getErrors(true) as $error) {
                $errors[$child->getName()] = $error->getMessage();
                break;
            }
        }

        if ([] === $errors) {
            foreach ($form->getErrors(true) as $error) {
                $origin = $error->getOrigin();
                if ($origin) {
                    $errors[$origin->getName()] = $error->getMessage();
                }
            }
        }

        return $errors;
    }
}
