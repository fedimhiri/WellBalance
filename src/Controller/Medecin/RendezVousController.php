<?php

namespace App\Controller\Medecin;

use App\Entity\User;
use App\Entity\RendezVous;
use App\Form\RendezVousMedecinType;
use App\Repository\RendezVousRepository;
use App\Repository\TypeRendezVousRepository;
use App\Service\RendezVousConflictManager;
use App\Service\RendezVousNotificationMailer;
use App\Service\RendezVousWorkflowManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/medecin/rdv', name: 'medecin_rendez_vous_')]
class RendezVousController extends AbstractController
{
    public function __construct(private readonly CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        RendezVousRepository $repository,
        TypeRendezVousRepository $typeRepository
    ): Response {
        if (($guard = $this->ensureMedecinAccess()) instanceof Response) {
            return $guard;
        }

        $filters = $this->extractAdvancedFilters($request);
        $result = $repository->searchAdvanced($filters);
        $calendarMedecinId = $this->resolveCalendarMedecinId($filters, $result['items']);

        return $this->render('frontend/medecin/rendez_vous/index.html.twig', [
            'rendezVousList' => $this->buildAppointmentRows($result['items']),
            'types' => $typeRepository->findBy([], ['nomType' => 'ASC']),
            'filters' => $filters,
            'calendarMedecinId' => $calendarMedecinId,
            'pagination' => [
                'page' => $result['page'],
                'pages' => $result['pages'],
                'total' => $result['total'],
                'limit' => $result['limit'],
            ],
        ]);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request, RendezVousRepository $repository): JsonResponse
    {
        if (($guard = $this->ensureMedecinAccess()) instanceof Response) {
            return new JsonResponse(['success' => false, 'redirect' => $guard->headers->get('Location')], Response::HTTP_FORBIDDEN);
        }

        $filters = $this->extractAdvancedFilters($request);
        $result = $repository->searchAdvanced($filters);
        $calendarMedecinId = $this->resolveCalendarMedecinId($filters, $result['items']);

        return $this->json([
            'items' => $this->buildAppointmentRows($result['items']),
            'calendarMedecinId' => $calendarMedecinId,
            'page' => $result['page'],
            'pages' => $result['pages'],
            'total' => $result['total'],
        ]);
    }

    #[Route('/api', name: 'api', methods: ['GET'])]
    public function api(Request $request, RendezVousRepository $repository): JsonResponse
    {
        if (($guard = $this->ensureMedecinAccess()) instanceof Response) {
            return new JsonResponse(['success' => false, 'redirect' => $guard->headers->get('Location')], Response::HTTP_FORBIDDEN);
        }

        return $this->search($request, $repository);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        RendezVousWorkflowManager $workflowManager,
        RendezVousConflictManager $conflictManager
    ): Response {
        if (($guard = $this->ensureMedecinAccess()) instanceof Response) {
            return $guard;
        }

        $rendezVous = new RendezVous();
        $rendezVous->setStatut(RendezVous::STATUT_EN_COURS);
        $responseStatus = Response::HTTP_OK;

        $form = $this->createForm(RendezVousMedecinType::class, $rendezVous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyBusinessErrorsToForm($form, $rendezVous, $workflowManager);
            $analysis = $conflictManager->analyze($rendezVous);

            if ($analysis['isPast']) {
                $form->get('heureRdv')->addError(new FormError('La date et l\'heure du rendez-vous ne peuvent pas etre dans le passe.'));
                $this->addFlash('error', 'Le formulaire contient des erreurs metier.');
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
                    $entityManager->getConnection()->transactional(function () use ($entityManager, $rendezVous): void {
                        $entityManager->persist($rendezVous);
                        $entityManager->flush();
                    });
                    $this->addFlash('success', 'Le rendez-vous a ete cree avec succes.');

                    return $this->redirectToRoute('medecin_rendez_vous_index');
                } catch (UniqueConstraintViolationException) {
                    $suggestions = $conflictManager->analyze($rendezVous)['suggestions'];
                    $form->get('heureRdv')->addError(new FormError('Creneau deja reserve'));
                    $this->addFlash('error', 'Creneau indisponible.');
                    $responseStatus = Response::HTTP_CONFLICT;

                    if ($this->expectsJson($request)) {
                        return $this->jsonConflictResponse($suggestions);
                    }
                }
            } else {
                $this->addFlash('error', 'Le formulaire contient des erreurs metier.');
            }
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs.');
        }

        return $this->render('rendez_vous_medecin/new.html.twig', [
            'form' => $form->createView(),
        ], new Response(status: $responseStatus));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(RendezVous $rendezVous): Response
    {
        if (($guard = $this->ensureMedecinAccess()) instanceof Response) {
            return $guard;
        }

        return $this->render('rendez_vous_medecin/show.html.twig', [
            'rendezVous' => $rendezVous,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        RendezVous $rendezVous,
        EntityManagerInterface $entityManager,
        RendezVousWorkflowManager $workflowManager,
        RendezVousConflictManager $conflictManager
    ): Response {
        if (($guard = $this->ensureMedecinAccess()) instanceof Response) {
            return $guard;
        }

        $originalStatus = $rendezVous->getStatut();
        $responseStatus = Response::HTTP_OK;
        $form = $this->createForm(RendezVousMedecinType::class, $rendezVous);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rendezVous->setStatut($originalStatus);
            $this->applyBusinessErrorsToForm($form, $rendezVous, $workflowManager);
            $analysis = $conflictManager->analyze($rendezVous, $rendezVous->getId());

            if ($analysis['isPast']) {
                $form->get('heureRdv')->addError(new FormError('La date et l\'heure du rendez-vous ne peuvent pas etre dans le passe.'));
                $this->addFlash('error', 'Le formulaire contient des erreurs metier.');
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
                    $entityManager->getConnection()->transactional(function () use ($entityManager): void {
                        $entityManager->flush();
                    });
                    $this->addFlash('success', 'Le rendez-vous a ete modifie avec succes.');

                    return $this->redirectToRoute('medecin_rendez_vous_show', ['id' => $rendezVous->getId()]);
                } catch (UniqueConstraintViolationException) {
                    $suggestions = $conflictManager->analyze($rendezVous, $rendezVous->getId())['suggestions'];
                    $form->get('heureRdv')->addError(new FormError('Creneau deja reserve'));
                    $this->addFlash('error', 'Creneau indisponible.');
                    $responseStatus = Response::HTTP_CONFLICT;

                    if ($this->expectsJson($request)) {
                        return $this->jsonConflictResponse($suggestions);
                    }
                }
            } else {
                $this->addFlash('error', 'Le formulaire contient des erreurs metier.');
            }
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Le formulaire contient des erreurs.');
        }

        return $this->render('rendez_vous_medecin/edit.html.twig', [
            'rendezVous' => $rendezVous,
            'form' => $form->createView(),
        ], new Response(status: $responseStatus));
    }

    #[Route('/{id}/accepter', name: 'accepter', methods: ['POST'])]
    public function accepter(
        Request $request,
        RendezVous $rendezVous,
        EntityManagerInterface $entityManager,
        RendezVousNotificationMailer $notificationMailer
    ): Response
    {
        if (($guard = $this->ensureMedecinAccess()) instanceof Response) {
            return $guard;
        }

        if (!$this->isCsrfTokenValid('rdv_action_'.$rendezVous->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        if (!$rendezVous->canAccept()) {
            $this->addFlash('error', 'Action impossible: seul un rendez-vous EN_COURS peut etre accepte.');

            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        $patientEmail = $rendezVous->getPatient()?->getEmail();
        if (null === $patientEmail || '' === trim($patientEmail)) {
            $this->addFlash('error', 'Action impossible: email patient manquant. Associez un patient avec une adresse email valide.');

            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        $rendezVous->setStatut(RendezVous::STATUT_ACCEPTE);
        $entityManager->flush();
        $mailSent = $notificationMailer->sendAccepted($rendezVous);

        $this->addFlash('success', 'Le rendez-vous a ete accepte.');
        if (!$mailSent) {
            $this->addFlash('warning', 'Le statut a ete mis a jour, mais l\'email n\'a pas pu etre envoye au patient.');
        }

        return $this->redirectToRoute('medecin_rendez_vous_index');
    }

    #[Route('/{id}/refuser', name: 'refuser', methods: ['POST'])]
    public function refuser(
        Request $request,
        RendezVous $rendezVous,
        EntityManagerInterface $entityManager,
        RendezVousNotificationMailer $notificationMailer
    ): Response
    {
        if (($guard = $this->ensureMedecinAccess()) instanceof Response) {
            return $guard;
        }

        if (!$this->isCsrfTokenValid('rdv_action_'.$rendezVous->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        if (!$rendezVous->canReject()) {
            $this->addFlash('error', 'Action impossible: seul un rendez-vous EN_COURS peut etre refuse.');

            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        $patientEmail = $rendezVous->getPatient()?->getEmail();
        if (null === $patientEmail || '' === trim($patientEmail)) {
            $this->addFlash('error', 'Action impossible: email patient manquant. Associez un patient avec une adresse email valide.');

            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        $rendezVous->setStatut(RendezVous::STATUT_REFUSE);
        $entityManager->flush();
        $mailSent = $notificationMailer->sendRefused($rendezVous);

        $this->addFlash('success', 'Le rendez-vous a ete refuse.');
        if (!$mailSent) {
            $this->addFlash('warning', 'Le statut a ete mis a jour, mais l\'email n\'a pas pu etre envoye au patient.');
        }

        return $this->redirectToRoute('medecin_rendez_vous_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, RendezVous $rendezVous, EntityManagerInterface $entityManager): Response
    {
        if (($guard = $this->ensureMedecinAccess()) instanceof Response) {
            return $guard;
        }

        if (!$this->isCsrfTokenValid('rdv_delete_'.$rendezVous->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('medecin_rendez_vous_index');
        }

        $entityManager->remove($rendezVous);
        $entityManager->flush();

        $this->addFlash('success', 'Le rendez-vous a ete supprime.');

        return $this->redirectToRoute('medecin_rendez_vous_index');
    }

    private function applyBusinessErrorsToForm(
        $form,
        RendezVous $rendezVous,
        RendezVousWorkflowManager $workflowManager
    ): void {
        foreach ($workflowManager->validateBusinessRules($rendezVous) as $error) {
            $form->addError(new FormError($error));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractAdvancedFilters(Request $request): array
    {
        $q = trim((string) $request->query->get('q', ''));
        $statutRaw = mb_strtoupper(trim((string) $request->query->get('statut', '')));
        $typeRaw = trim((string) $request->query->get('type', ''));
        $dateFromRaw = trim((string) $request->query->get('date_from', ''));
        $dateToRaw = trim((string) $request->query->get('date_to', ''));
        $sortRaw = (string) $request->query->get('sort', 'dateRdv');
        $directionRaw = mb_strtolower((string) $request->query->get('direction', 'desc'));
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        $limit = max(5, min(50, $limit));

        $sortWhitelist = ['dateRdv', 'statut', 'type', 'patient'];
        $sort = in_array($sortRaw, $sortWhitelist, true) ? $sortRaw : 'dateRdv';
        $direction = in_array($directionRaw, ['asc', 'desc'], true) ? $directionRaw : 'desc';
        $statut = in_array($statutRaw, RendezVous::STATUTS, true) ? $statutRaw : null;
        $type = ctype_digit($typeRaw) && (int) $typeRaw > 0 ? (int) $typeRaw : null;

        $dateFrom = $this->parseDateOrNull($dateFromRaw);
        $dateTo = $this->parseDateOrNull($dateToRaw);

        if ($dateFrom instanceof \DateTimeImmutable && $dateTo instanceof \DateTimeImmutable && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $medecinId = $request->query->getInt('medecin_id', 0);

        return [
            'q' => $q,
            'statut' => $statut,
            'type' => $type,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
            'limit' => $limit,
            'medecin_id' => $medecinId > 0 ? $medecinId : null,
            'date_from_raw' => $dateFrom?->format('Y-m-d') ?? '',
            'date_to_raw' => $dateTo?->format('Y-m-d') ?? '',
        ];
    }

    private function parseDateOrNull(string $value): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            false === $date
            || ($errors['warning_count'] ?? 0) > 0
            || ($errors['error_count'] ?? 0) > 0
        ) {
            return null;
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $filters
     * @param RendezVous[]         $items
     */
    private function resolveCalendarMedecinId(array $filters, array $items): ?int
    {
        $requestedMedecinId = isset($filters['medecin_id']) ? (int) $filters['medecin_id'] : 0;
        if ($requestedMedecinId > 0) {
            return $requestedMedecinId;
        }

        foreach ($items as $item) {
            $medecinId = $item->getMedecin()?->getId();
            if (null !== $medecinId) {
                return (int) $medecinId;
            }
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && null !== $currentUser->getId()) {
            return (int) $currentUser->getId();
        }

        return null;
    }

    /**
     * @param RendezVous[] $rendezVousList
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildAppointmentRows(array $rendezVousList): array
    {
        return array_map(function (RendezVous $rendezVous): array {
            $patient = $rendezVous->getPatient();
            $patientName = '';
            if (null !== $patient) {
                $patientName = $patient->getDisplayName();
            }

            return [
                'id' => $rendezVous->getId(),
                'titre' => (string) ($rendezVous->getTitre() ?? ''),
                'date' => $rendezVous->getDateRdv()?->format('Y-m-d') ?? '',
                'heure' => $rendezVous->getHeureRdv()?->format('H:i') ?? '',
                'statut' => $rendezVous->getStatut(),
                'type' => $rendezVous->getTypeRendezVous()?->getNomType() ?? '',
                'patient' => $patientName,
                'canAcceptOrReject' => $rendezVous->canAccept(),
                'csrfActionToken' => $this->csrfTokenManager->getToken('rdv_action_'.$rendezVous->getId())->getValue(),
                'csrfDeleteToken' => $this->csrfTokenManager->getToken('rdv_delete_'.$rendezVous->getId())->getValue(),
            ];
        }, $rendezVousList);
    }

    private function expectsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || 'json' === $request->getRequestFormat()
            || str_contains((string) $request->headers->get('Accept'), 'application/json');
    }

    private function ensureMedecinAccess(): ?Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $roles = $user->getRoles();
        if (!in_array('ROLE_MEDECIN', $roles, true) && !in_array('ROLE_ADMIN', $roles, true)) {
            return $this->redirectToRoute('app_frontend_vue');
        }

        return null;
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
}
