<?php

namespace App\Entity;

use App\Repository\AlerteNutritionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlerteNutritionRepository::class)]
#[ORM\Table(name: 'alerte_nutrition')]
class AlerteNutrition
{
    public const TYPE_RESPECT_FAIBLE = 'RESPECT_FAIBLE';
    public const TYPE_PERTE_RAPIDE   = 'PERTE_POIDS_RAPIDE';
    public const TYPE_SANS_SUIVI     = 'SANS_SUIVI';

    public const SEV_INFO = 'INFO';
    public const SEV_WARN = 'WARN';
    public const SEV_CRIT = 'CRIT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $type;

    #[ORM\Column(length: 10)]
    private string $severity = self::SEV_INFO;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(name: 'resolved_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resolvedAt = null;

    #[ORM\Column(name: 'is_read', type: 'boolean', options: ['default' => false])]
    private bool $isRead = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'patient_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $patient = null;

    #[ORM\ManyToOne(targetEntity: PlanNutrition::class)]
    #[ORM\JoinColumn(name: 'plan_nutrition_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?PlanNutrition $planNutrition = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'nutritionniste_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $nutritionniste = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getSeverity(): string { return $this->severity; }
    public function setSeverity(string $severity): static { $this->severity = $severity; return $this; }

    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getResolvedAt(): ?\DateTimeInterface { return $this->resolvedAt; }
    public function setResolvedAt(?\DateTimeInterface $resolvedAt): static { $this->resolvedAt = $resolvedAt; return $this; }

    public function resolve(): static { $this->resolvedAt = new \DateTime(); return $this; }

    public function getIsRead(): bool { return $this->isRead; }
    public function isRead(): bool { return $this->isRead; } // garde si tu l'utilises en twig
    public function setIsRead(bool $isRead): static { $this->isRead = $isRead; return $this; }

    public function getPatient(): ?User { return $this->patient; }
    public function setPatient(?User $patient): static { $this->patient = $patient; return $this; }

    public function getPlanNutrition(): ?PlanNutrition { return $this->planNutrition; }
    public function setPlanNutrition(?PlanNutrition $planNutrition): static { $this->planNutrition = $planNutrition; return $this; }

    public function getNutritionniste(): ?User { return $this->nutritionniste; }
    public function setNutritionniste(?User $nutritionniste): static { $this->nutritionniste = $nutritionniste; return $this; }
}
