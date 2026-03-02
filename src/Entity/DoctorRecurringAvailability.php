<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DoctorRecurringAvailabilityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DoctorRecurringAvailabilityRepository::class)]
#[ORM\Table(name: 'doctor_recurring_availability')]
class DoctorRecurringAvailability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\Positive]
    private int $doctorId = 1;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 7)]
    private int $weekday = 1;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $endTime;

    #[ORM\Column]
    #[Assert\Choice(choices: [15, 30, 60])]
    private int $slotMinutes = 30;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDoctorId(): int
    {
        return $this->doctorId;
    }

    public function setDoctorId(int $doctorId): self
    {
        $this->doctorId = $doctorId;

        return $this;
    }

    public function getWeekday(): int
    {
        return $this->weekday;
    }

    public function setWeekday(int $weekday): self
    {
        $this->weekday = $weekday;

        return $this;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeImmutable $startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeImmutable $endTime): self
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getSlotMinutes(): int
    {
        return $this->slotMinutes;
    }

    public function setSlotMinutes(int $slotMinutes): self
    {
        $this->slotMinutes = $slotMinutes;

        return $this;
    }
}
