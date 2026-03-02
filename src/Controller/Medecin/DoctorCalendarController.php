<?php

declare(strict_types=1);

namespace App\Controller\Medecin;

use App\Dto\Calendar\AvailabilityExceptionInputDto;
use App\Dto\Calendar\RecurringAvailabilityInputDto;
use App\Dto\Calendar\TimeBlockInputDto;
use App\Entity\DoctorAvailabilityException;
use App\Service\DoctorCalendarService;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/calendar/doctor', name: 'calendar_doctor_')]
class DoctorCalendarController extends AbstractController
{
    #[Route('', name: 'get', methods: ['GET'])]
    public function getCalendar(Request $request, DoctorCalendarService $calendarService): JsonResponse
    {
        $doctorId = max(1, $request->query->getInt('doctorId', 1));
        $view = mb_strtolower((string) $request->query->get('view', 'month'));
        if (!in_array($view, ['month', 'week', 'day'], true)) {
            $view = 'month';
        }

        $from = $this->parseDate((string) $request->query->get('from', ''));
        $to = $this->parseDate((string) $request->query->get('to', ''));

        if (null === $from || null === $to) {
            return $this->json([
                'success' => false,
                'message' => 'Parametres invalides',
                'errors' => [
                    'from' => 'Format YYYY-MM-DD attendu.',
                    'to' => 'Format YYYY-MM-DD attendu.',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        if ($to->diff($from)->days > 93) {
            return $this->json([
                'success' => false,
                'message' => 'Plage trop grande',
                'errors' => [
                    'range' => 'Maximum 93 jours par requete.',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $calendar = $calendarService->buildCalendar($doctorId, $from, $to, $view);
        } catch (TableNotFoundException) {
            return $this->json([
                'success' => false,
                'message' => 'Tables calendrier absentes. Executez les migrations du module calendrier.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (\Throwable) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur interne du module calendrier.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'data' => $calendar->toArray(),
        ]);
    }

    #[Route('/recurring', name: 'recurring_upsert', methods: ['POST'])]
    public function upsertRecurring(Request $request, DoctorCalendarService $calendarService): JsonResponse
    {
        $payload = $this->decodeJson($request);
        if (null === $payload) {
            return $this->json(['success' => false, 'message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $dto = new RecurringAvailabilityInputDto(
                id: isset($payload['id']) ? (int) $payload['id'] : null,
                doctorId: max(1, (int) ($payload['doctorId'] ?? 1)),
                weekday: (int) ($payload['weekday'] ?? 0),
                startTime: (string) ($payload['startTime'] ?? ''),
                endTime: (string) ($payload['endTime'] ?? ''),
                slotMinutes: (int) ($payload['slotMinutes'] ?? 30)
            );

            if (!in_array($dto->slotMinutes, [15, 30, 60], true) || $dto->weekday < 1 || $dto->weekday > 7) {
                throw new \InvalidArgumentException('weekday/slotMinutes invalid');
            }

            $entity = $calendarService->upsertRecurring($dto);

            return $this->json([
                'success' => true,
                'data' => [
                    'id' => $entity->getId(),
                    'doctorId' => $entity->getDoctorId(),
                    'weekday' => $entity->getWeekday(),
                    'startTime' => $entity->getStartTime()->format('H:i'),
                    'endTime' => $entity->getEndTime()->format('H:i'),
                    'slotMinutes' => $entity->getSlotMinutes(),
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/exceptions', name: 'exceptions_create', methods: ['POST'])]
    public function createException(Request $request, DoctorCalendarService $calendarService): JsonResponse
    {
        $payload = $this->decodeJson($request);
        if (null === $payload) {
            return $this->json(['success' => false, 'message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $dto = new AvailabilityExceptionInputDto(
                doctorId: max(1, (int) ($payload['doctorId'] ?? 1)),
                date: (string) ($payload['date'] ?? ''),
                type: (string) ($payload['type'] ?? DoctorAvailabilityException::TYPE_FULL_DAY),
                startTime: isset($payload['startTime']) ? (string) $payload['startTime'] : null,
                endTime: isset($payload['endTime']) ? (string) $payload['endTime'] : null,
            );

            if (!in_array($dto->type, [DoctorAvailabilityException::TYPE_FULL_DAY, DoctorAvailabilityException::TYPE_PARTIAL], true)) {
                throw new \InvalidArgumentException('type invalid');
            }

            $entity = $calendarService->createException($dto);

            return $this->json([
                'success' => true,
                'data' => [
                    'id' => $entity->getId(),
                    'doctorId' => $entity->getDoctorId(),
                    'date' => $entity->getDate()->format('Y-m-d'),
                    'type' => $entity->getType(),
                    'startTime' => $entity->getStartTime()?->format('H:i'),
                    'endTime' => $entity->getEndTime()?->format('H:i'),
                ],
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/blocks', name: 'blocks_create', methods: ['POST'])]
    public function createBlock(Request $request, DoctorCalendarService $calendarService): JsonResponse
    {
        $payload = $this->decodeJson($request);
        if (null === $payload) {
            return $this->json(['success' => false, 'message' => 'JSON invalide'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $dto = new TimeBlockInputDto(
                doctorId: max(1, (int) ($payload['doctorId'] ?? 1)),
                startDatetime: (string) ($payload['startDatetime'] ?? ''),
                endDatetime: (string) ($payload['endDatetime'] ?? ''),
                note: isset($payload['note']) ? (string) $payload['note'] : null
            );

            $entity = $calendarService->createBlock($dto);

            return $this->json([
                'success' => true,
                'data' => [
                    'id' => $entity->getId(),
                    'doctorId' => $entity->getDoctorId(),
                    'startDatetime' => $entity->getStartDatetime()->format(\DateTimeInterface::ATOM),
                    'endDatetime' => $entity->getEndDatetime()->format(\DateTimeInterface::ATOM),
                    'note' => $entity->getNote(),
                ],
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/exceptions/{id}', name: 'exceptions_delete', methods: ['DELETE'])]
    public function deleteException(int $id, Request $request, DoctorCalendarService $calendarService): JsonResponse
    {
        $doctorId = max(1, $request->query->getInt('doctorId', 1));
        $deleted = $calendarService->deleteException($id, $doctorId);

        return $this->json([
            'success' => $deleted,
            'message' => $deleted ? 'Exception supprimee' : 'Exception introuvable',
        ], $deleted ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);
    }

    #[Route('/blocks/{id}', name: 'blocks_delete', methods: ['DELETE'])]
    public function deleteBlock(int $id, Request $request, DoctorCalendarService $calendarService): JsonResponse
    {
        $doctorId = max(1, $request->query->getInt('doctorId', 1));
        $deleted = $calendarService->deleteBlock($id, $doctorId);

        return $this->json([
            'success' => $deleted,
            'message' => $deleted ? 'Blocage supprime' : 'Blocage introuvable',
        ], $deleted ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(Request $request): ?array
    {
        $content = trim($request->getContent());
        if ('' === $content) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date) {
            return null;
        }

        return $date->setTime(0, 0, 0);
    }
}
