<?php

namespace App\Entity;

use App\Repository\RepasRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RepasRepository::class)]
class Repas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $typeRepas = null;

    #[ORM\Column]
    private ?int $calories = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    // ✅ DATETIME au lieu de DATE
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateRepas = null;

    #[ORM\ManyToOne(inversedBy: 'repas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanNutrition $planNutrition = null;

    public function getId(): ?int { return $this->id; }

    public function getTypeRepas(): ?string { return $this->typeRepas; }
    public function setTypeRepas(string $typeRepas): static { $this->typeRepas = $typeRepas; return $this; }

    public function getCalories(): ?int { return $this->calories; }
    public function setCalories(int $calories): static { $this->calories = $calories; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getDateRepas(): ?\DateTimeInterface { return $this->dateRepas; }
    public function setDateRepas(\DateTimeInterface $dateRepas): static { $this->dateRepas = $dateRepas; return $this; }

    public function getPlanNutrition(): ?PlanNutrition { return $this->planNutrition; }
    public function setPlanNutrition(?PlanNutrition $planNutrition): static { $this->planNutrition = $planNutrition; return $this; }
}
