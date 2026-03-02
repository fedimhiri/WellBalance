<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DoctorTimeBlockRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DoctorTimeBlockRepository::class)]
#[ORM\Table(name: 'doctor_time_block')]
class DoctorTimeBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\Positive]
    private int $doctorId = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startDatetime;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $endDatetime;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

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

    public function getStartDatetime(): \DateTimeImmutable
    {
        return $this->startDatetime;
    }

    public function setStartDatetime(\DateTimeImmutable $startDatetime): self
    {
        $this->startDatetime = $startDatetime;

        return $this;
    }

    public function getEndDatetime(): \DateTimeImmutable
    {
        return $this->endDatetime;
    }

    public function setEndDatetime(\DateTimeImmutable $endDatetime): self
    {
        $this->endDatetime = $endDatetime;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }
}
