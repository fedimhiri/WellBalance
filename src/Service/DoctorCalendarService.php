<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Calendar\AvailabilityExceptionInputDto;
use App\Dto\Calendar\CalendarDayDto;
use App\Dto\Calendar\CalendarSlotDto;
use App\Dto\Calendar\DoctorCalendarResponseDto;
use App\Dto\Calendar\RecurringAvailabilityInputDto;
use App\Dto\Calendar\TimeBlockInputDto;
use App\Entity\DoctorAvailabilityException;
use App\Entity\DoctorRecurringAvailability;
use App\Entity\DoctorTimeBlock;
use App\Entity\RendezVous;
use App\Repository\DoctorAvailabilityExceptionRepository;
use App\Repository\DoctorRecurringAvailabilityRepository;
use App\Repository\DoctorTimeBlockRepository;
use App\Repository\RendezVousRepository;
use Doctrine\ORM\EntityManagerInterface;

class DoctorCalendarService
{
    public const STATE_AVAILABLE = 'AVAILABLE';
    public const STATE_UNAVAILABLE = 'UNAVAILABLE';
    public const STATE_BUSY = 'BUSY';
    private const DEFAULT_SLOT_MINUTES = 30;

    public function __construct(
        private readonly DoctorRecurringAvailabilityRepository $recurringRepository,
        private readonly DoctorAvailabilityExceptionRepository $exceptionRepository,
        private readonly DoctorTimeBlockRepository $blockRepository,
        private readonly RendezVousRepository $rendezVousRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function buildCalendar(
        int $doctorId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        string $view
    ): DoctorCalendarResponseDto {
        $from = $from->setTime(0, 0, 0);
        $to = $to->setTime(0, 0, 0);

        $recurringRows = $this->recurringRepository->findForDoctor($doctorId);
        $exceptions = $this->exceptionRepository->findForDoctorBetween($doctorId, $from, $to);
        $blocks = $this->blockRepository->findForDoctorBetween($doctorId, $from, $to);
        $appointments = $this->rendezVousRepository->findCalendarAppointmentsBetween($doctorId, $from, $to);

        $recurringByWeekday = [];
        foreach ($recurringRows as $row) {
            $recurringByWeekday[$row->getWeekday()][] = $row;
        }

        $exceptionsByDate = [];
        foreach ($exceptions as $exception) {
            $exceptionsByDate[$exception->getDate()->format('Y-m-d')][] = $exception;
        }

        $appointmentsByDate = [];
        foreach ($appointments as $appointment) {
            $appointmentsByDate[$appointment['date']][] = $appointment;
        }

        $days = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $days[] = $this->buildDay(
                $day,
                $recurringByWeekday[(int) $day->format('N')] ?? [],
                $exceptionsByDate[$day->format('Y-m-d')] ?? [],
                $blocks,
                $appointmentsByDate[$day->format('Y-m-d')] ?? []
            );
        }

        return new DoctorCalendarResponseDto(
            doctorId: $doctorId,
            view: $view,
            from: $from->format('Y-m-d'),
            to: $to->format('Y-m-d'),
            days: $days
        );
    }

    public function upsertRecurring(RecurringAvailabilityInputDto $input): DoctorRecurringAvailability
    {
        $entity = null;
        if (null !== $input->id) {
            $entity = $this->recurringRepository->find($input->id);
        }

        if (!$entity instanceof DoctorRecurringAvailability) {
            $entity = new DoctorRecurringAvailability();
        }

        $entity
            ->setDoctorId($input->doctorId)
            ->setWeekday($input->weekday)
            ->setStartTime($this->parseTime($input->startTime))
            ->setEndTime($this->parseTime($input->endTime))
            ->setSlotMinutes($input->slotMinutes);

        if ($entity->getStartTime() >= $entity->getEndTime()) {
            throw new \InvalidArgumentException('startTime must be before endTime');
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    public function createException(AvailabilityExceptionInputDto $input): DoctorAvailabilityException
    {
        $entity = new DoctorAvailabilityException();
        $entity
            ->setDoctorId($input->doctorId)
            ->setDate($this->parseDate($input->date))
            ->setType($input->type);

        if (DoctorAvailabilityException::TYPE_PARTIAL === $input->type) {
            $start = $this->parseTime((string) $input->startTime);
            $end = $this->parseTime((string) $input->endTime);
            if ($start >= $end) {
                throw new \InvalidArgumentException('startTime must be before endTime');
            }
            $entity->setStartTime($start)->setEndTime($end);
        } else {
            $entity->setStartTime(null)->setEndTime(null);
        }

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    public function createBlock(TimeBlockInputDto $input): DoctorTimeBlock
    {
        $start = $this->parseDateTime($input->startDatetime);
        $end = $this->parseDateTime($input->endDatetime);
        if ($start >= $end) {
            throw new \InvalidArgumentException('startDatetime must be before endDatetime');
        }

        $entity = new DoctorTimeBlock();
        $entity
            ->setDoctorId($input->doctorId)
            ->setStartDatetime($start)
            ->setEndDatetime($end)
            ->setNote($input->note);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    public function deleteException(int $id, int $doctorId): bool
    {
        $entity = $this->exceptionRepository->find($id);
        if (!$entity instanceof DoctorAvailabilityException || $entity->getDoctorId() !== $doctorId) {
            return false;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return true;
    }

    public function deleteBlock(int $id, int $doctorId): bool
    {
        $entity = $this->blockRepository->find($id);
        if (!$entity instanceof DoctorTimeBlock || $entity->getDoctorId() !== $doctorId) {
            return false;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @param DoctorRecurringAvailability[] $recurringRows
     * @param DoctorAvailabilityException[] $exceptions
     * @param DoctorTimeBlock[] $blocks
     * @param array<int, array{id:int,date:string,time:string,status:string}> $appointments
     */
    private function buildDay(
        \DateTimeImmutable $day,
        array $recurringRows,
        array $exceptions,
        array $blocks,
        array $appointments
    ): CalendarDayDto {
        $slots = [];
        $defaultSlotMinutes = self::DEFAULT_SLOT_MINUTES;

        foreach ($recurringRows as $row) {
            $slotMinutes = $row->getSlotMinutes();
            $defaultSlotMinutes = $slotMinutes;
            $start = $this->atTime($day, $row->getStartTime());
            $end = $this->atTime($day, $row->getEndTime());

            for ($cursor = $start; $cursor < $end; $cursor = $cursor->modify(sprintf('+%d minutes', $slotMinutes))) {
                $slotEnd = $cursor->modify(sprintf('+%d minutes', $slotMinutes));
                if ($slotEnd > $end) {
                    break;
                }

                $key = $cursor->format('H:i');
                $slots[$key] = new CalendarSlotDto(
                    start: $cursor->format('H:i'),
                    end: $slotEnd->format('H:i'),
                    state: self::STATE_AVAILABLE,
                    reason: 'recurring'
                );
            }
        }

        foreach ($exceptions as $exception) {
            if (DoctorAvailabilityException::TYPE_FULL_DAY === $exception->getType()) {
                foreach ($slots as $key => $slot) {
                    $slots[$key] = new CalendarSlotDto(
                        start: $slot->start,
                        end: $slot->end,
                        state: self::STATE_UNAVAILABLE,
                        reason: 'full_day_exception',
                        pendingCount: $slot->pendingCount
                    );
                }
                continue;
            }

            $startTime = $exception->getStartTime();
            $endTime = $exception->getEndTime();
            if (null === $startTime || null === $endTime) {
                continue;
            }

            $exceptionStart = $this->atTime($day, $startTime);
            $exceptionEnd = $this->atTime($day, $endTime);
            foreach ($slots as $key => $slot) {
                $slotStart = $this->atTimeFromString($day, $slot->start);
                $slotEnd = $this->atTimeFromString($day, $slot->end);
                if ($this->overlap($slotStart, $slotEnd, $exceptionStart, $exceptionEnd)) {
                    $slots[$key] = new CalendarSlotDto(
                        start: $slot->start,
                        end: $slot->end,
                        state: self::STATE_UNAVAILABLE,
                        reason: 'partial_exception',
                        pendingCount: $slot->pendingCount
                    );
                }
            }
        }

        foreach ($blocks as $block) {
            foreach ($slots as $key => $slot) {
                $slotStart = $this->atTimeFromString($day, $slot->start);
                $slotEnd = $this->atTimeFromString($day, $slot->end);
                if ($this->overlap($slotStart, $slotEnd, $block->getStartDatetime(), $block->getEndDatetime())) {
                    $slots[$key] = new CalendarSlotDto(
                        start: $slot->start,
                        end: $slot->end,
                        state: self::STATE_UNAVAILABLE,
                        reason: 'manual_block',
                        pendingCount: $slot->pendingCount
                    );
                }
            }
        }

        $pendingByKey = [];
        foreach ($appointments as $appointment) {
            if (RendezVous::STATUT_EN_COURS === $appointment['status']) {
                $pendingByKey[$appointment['time']] = ($pendingByKey[$appointment['time']] ?? 0) + 1;
            }
        }

        foreach ($appointments as $appointment) {
            $time = $appointment['time'];
            $status = $appointment['status'];

            if (RendezVous::STATUT_ACCEPTE !== $status && RendezVous::STATUT_EN_COURS !== $status) {
                continue;
            }

            $slotMinutes = $this->resolveSlotMinutesAtTime($recurringRows, $time, $defaultSlotMinutes);
            $start = $this->atTimeFromString($day, $time);
            $end = $start->modify(sprintf('+%d minutes', $slotMinutes));
            $key = $start->format('H:i');
            $pendingCount = $pendingByKey[$key] ?? 0;

            // Un creneau reserve (ACCEPTE ou EN_COURS) doit apparaitre BUSY dans le calendrier.
            $slots[$key] = new CalendarSlotDto(
                start: $start->format('H:i'),
                end: $end->format('H:i'),
                state: self::STATE_BUSY,
                reason: RendezVous::STATUT_ACCEPTE === $status ? 'confirmed_appointment' : 'pending_appointment',
                pendingCount: $pendingCount
            );
        }

        ksort($slots);
        $slotList = array_values($slots);
        $totalSlots = count($slotList);
        $availableCount = count(array_filter(
            $slotList,
            static fn (CalendarSlotDto $slot): bool => self::STATE_AVAILABLE === $slot->state
        ));
        $busyCount = count(array_filter(
            $slotList,
            static fn (CalendarSlotDto $slot): bool => self::STATE_BUSY === $slot->state
        ));
        $unavailableCount = count(array_filter(
            $slotList,
            static fn (CalendarSlotDto $slot): bool => self::STATE_UNAVAILABLE === $slot->state
        ));
        $pendingCount = array_reduce(
            $slotList,
            static fn (int $carry, CalendarSlotDto $slot): int => $carry + $slot->pendingCount,
            0
        );

        $dayStatus = self::STATE_UNAVAILABLE;
        if ($availableCount > 0) {
            $dayStatus = self::STATE_AVAILABLE;
        } elseif (0 === $availableCount && $busyCount > 0) {
            $dayStatus = self::STATE_BUSY;
        } elseif (0 === $totalSlots || $unavailableCount === $totalSlots) {
            $dayStatus = self::STATE_UNAVAILABLE;
        }

        return new CalendarDayDto(
            date: $day->format('Y-m-d'),
            isAvailable: $availableCount > 0,
            slots: $slotList,
            totalSlots: $totalSlots,
            availableSlots: $availableCount,
            busySlots: $busyCount,
            unavailableSlots: $unavailableCount,
            dayStatus: $dayStatus,
            pendingCount: $pendingCount
        );
    }

    /**
     * @param DoctorRecurringAvailability[] $recurringRows
     */
    private function resolveSlotMinutesAtTime(array $recurringRows, string $time, int $fallback): int
    {
        foreach ($recurringRows as $row) {
            if ($time >= $row->getStartTime()->format('H:i') && $time < $row->getEndTime()->format('H:i')) {
                return $row->getSlotMinutes();
            }
        }

        return $fallback;
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (false === $parsed) {
            throw new \InvalidArgumentException('Invalid date format');
        }

        return $parsed;
    }

    private function parseTime(string $time): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!H:i', $time);
        if (false === $parsed) {
            throw new \InvalidArgumentException('Invalid time format');
        }

        return $parsed;
    }

    private function parseDateTime(string $datetime): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($datetime);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Invalid datetime format');
        }
    }

    private function atTime(\DateTimeImmutable $day, \DateTimeImmutable $time): \DateTimeImmutable
    {
        return $day->setTime(
            (int) $time->format('H'),
            (int) $time->format('i'),
            0
        );
    }

    private function atTimeFromString(\DateTimeImmutable $day, string $time): \DateTimeImmutable
    {
        [$hour, $minutes] = explode(':', $time);

        return $day->setTime((int) $hour, (int) $minutes, 0);
    }

    private function overlap(
        \DateTimeImmutable $startA,
        \DateTimeImmutable $endA,
        \DateTimeImmutable $startB,
        \DateTimeImmutable $endB
    ): bool {
        return $startA < $endB && $startB < $endA;
    }
}
