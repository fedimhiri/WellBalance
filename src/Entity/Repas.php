<?php

namespace App\Entity;

use App\Repository\RepasRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RepasRepository::class)]
class Repas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le type de repas est obligatoire.')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le type doit contenir au moins {{ limit }} caractères.')]
    private ?string $typeRepas = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Les calories sont obligatoires.')]
    #[Assert\Range(min: 0, max: 5000, notInRangeMessage: 'Les calories doivent être entre {{ min }} et {{ max }}.')]
    private ?int $calories = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 5, minMessage: 'La description doit contenir au moins {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date du repas est obligatoire.')]
    private ?\DateTimeInterface $dateRepas = null;

    #[ORM\ManyToOne(inversedBy: 'repas')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Veuillez sélectionner un plan nutritionnel.')]
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
