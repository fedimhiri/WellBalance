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

    #[ORM\Column(nullable: true)]
    private ?int $calories = null;

    #[ORM\Column(nullable: true)]
    private ?float $proteines = null;

    #[ORM\Column(nullable: true)]
    private ?float $glucides = null;

    #[ORM\Column(nullable: true)]
    private ?float $lipides = null;

    // ✅ IMPORTANT : column name = portion_size
    #[ORM\Column(name: 'portion_size', length: 50, nullable: true)]
    private ?string $portionSize = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateRepas = null;

    #[ORM\ManyToOne(inversedBy: 'repas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanNutrition $planNutrition = null;

    public function getId(): ?int { return $this->id; }

    public function getTypeRepas(): ?string { return $this->typeRepas; }
    public function setTypeRepas(string $typeRepas): static { $this->typeRepas = $typeRepas; return $this; }

    public function getCalories(): ?int { return $this->calories; }
    public function setCalories(?int $calories): static { $this->calories = $calories; return $this; }

    public function getProteines(): ?float { return $this->proteines; }
    public function setProteines(?float $proteines): static { $this->proteines = $proteines; return $this; }

    public function getGlucides(): ?float { return $this->glucides; }
    public function setGlucides(?float $glucides): static { $this->glucides = $glucides; return $this; }

    public function getLipides(): ?float { return $this->lipides; }
    public function setLipides(?float $lipides): static { $this->lipides = $lipides; return $this; }

    public function getPortionSize(): ?string { return $this->portionSize; }
    public function setPortionSize(?string $portionSize): static { $this->portionSize = $portionSize; return $this; }

    public function getBarcode(): ?string { return $this->barcode; }
    public function setBarcode(?string $barcode): static { $this->barcode = $barcode; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getDateRepas(): ?\DateTimeInterface { return $this->dateRepas; }
    public function setDateRepas(\DateTimeInterface $dateRepas): static { $this->dateRepas = $dateRepas; return $this; }

    public function getPlanNutrition(): ?PlanNutrition { return $this->planNutrition; }
    public function setPlanNutrition(?PlanNutrition $planNutrition): static { $this->planNutrition = $planNutrition; return $this; }
}
