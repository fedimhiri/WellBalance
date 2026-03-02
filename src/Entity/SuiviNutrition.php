<?php

namespace App\Entity;

use App\Repository\SuiviNutritionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SuiviNutritionRepository::class)]
class SuiviNutrition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $dateSuivi = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?float $poids = null;

    #[ORM\Column(nullable: false)]
    #[Assert\Range(min: 0, max: 100)]
    private int $respectPourcentage = 0;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $humeur = null; // Bien / Moyen / Mal

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentairePatient = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaireNutritionniste = null;

    #[ORM\ManyToOne(inversedBy: 'suivis')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanNutrition $planNutrition = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $patient = null;

    public function __construct()
    {
        $this->dateSuivi = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getDateSuivi(): ?\DateTimeInterface { return $this->dateSuivi; }
    public function setDateSuivi(\DateTimeInterface $dateSuivi): static { $this->dateSuivi = $dateSuivi; return $this; }

    public function getPoids(): ?float { return $this->poids; }
    public function setPoids(?float $poids): static { $this->poids = $poids; return $this; }

    public function getRespectPourcentage(): int { return $this->respectPourcentage; }
    public function setRespectPourcentage(int $respectPourcentage): static { $this->respectPourcentage = $respectPourcentage; return $this; }

    public function getHumeur(): ?string { return $this->humeur; }
    public function setHumeur(?string $humeur): static { $this->humeur = $humeur; return $this; }

    public function getCommentairePatient(): ?string { return $this->commentairePatient; }
    public function setCommentairePatient(?string $commentairePatient): static { $this->commentairePatient = $commentairePatient; return $this; }

    public function getCommentaireNutritionniste(): ?string { return $this->commentaireNutritionniste; }
    public function setCommentaireNutritionniste(?string $commentaireNutritionniste): static { $this->commentaireNutritionniste = $commentaireNutritionniste; return $this; }

    public function getPlanNutrition(): ?PlanNutrition { return $this->planNutrition; }
    public function setPlanNutrition(?PlanNutrition $planNutrition): static { $this->planNutrition = $planNutrition; return $this; }

    public function getPatient(): ?User { return $this->patient; }
    public function setPatient(?User $patient): static { $this->patient = $patient; return $this; }
}
