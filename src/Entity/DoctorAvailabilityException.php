<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DoctorAvailabilityExceptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DoctorAvailabilityExceptionRepository::class)]
#[ORM\Table(name: 'doctor_availability_exception')]
class DoctorAvailabilityException
{
    public const TYPE_FULL_DAY = 'FULL_DAY';
    public const TYPE_PARTIAL = 'PARTIAL';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\Positive]
    private int $doctorId = 1;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::TYPE_FULL_DAY, self::TYPE_PARTIAL])]
    private string $type = self::TYPE_FULL_DAY;

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

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeImmutable $startTime): self
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): self
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
