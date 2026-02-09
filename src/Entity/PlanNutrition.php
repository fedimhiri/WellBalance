<?php

namespace App\Entity;

use App\Repository\PlanNutritionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlanNutritionRepository::class)]
class PlanNutrition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'objectif est obligatoire.")]
    private ?string $objectif = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $periode = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: "La date de début est obligatoire.")]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: "La date de fin est obligatoire.")]
    #[Assert\GreaterThanOrEqual(
        propertyPath: "dateDebut",
        message: "La date de fin doit être supérieure ou égale à la date de début."
    )]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\ManyToOne(inversedBy: 'planNutritions')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Veuillez sélectionner un utilisateur.")]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'planNutrition', targetEntity: Repas::class, cascade: ['persist', 'remove'])]
    private Collection $repas;

    public function __construct()
    {
        $this->repas = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getObjectif(): ?string { return $this->objectif; }

    public function setObjectif(string $objectif): static
    {
        $this->objectif = $objectif;
        return $this;
    }

    public function getDescription(): ?string { return $this->description; }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPeriode(): ?string { return $this->periode; }

    public function setPeriode(?string $periode): static
    {
        $this->periode = $periode;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getUser(): ?User { return $this->user; }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, Repas>
     */
    public function getRepas(): Collection
    {
        return $this->repas;
    }

    public function addRepa(Repas $repa): static
    {
        if (!$this->repas->contains($repa)) {
            $this->repas->add($repa);
            $repa->setPlanNutrition($this);
        }
        return $this;
    }

    public function removeRepa(Repas $repa): static
    {
        if ($this->repas->removeElement($repa)) {
            if ($repa->getPlanNutrition() === $this) {
                $repa->setPlanNutrition(null);
            }
        }
        return $this;
    }

    // ========== MÉTHODES PERSONNALISÉES ==========
    public function calculerPeriode(): string
    {
        if (!$this->dateDebut || !$this->dateFin) {
            return 'Période non définie';
        }

        $interval = $this->dateDebut->diff($this->dateFin);
        $jours = $interval->days;

        if ($jours >= 30) {
            $mois = floor($jours / 30);
            return $mois . ' mois' . ($mois > 1 ? 's' : '');
        }

        return $jours . ' jour' . ($jours > 1 ? 's' : '');
    }

    public function getStats(): array
    {
        $totalCalories = 0;
        $repasParType = [];
        $caloriesParType = [];

        foreach ($this->getRepas() as $repa) {
            $calories = $repa->getCalories() ?? 0;
            $totalCalories += $calories;

            $type = $repa->getTypeRepas() ?? 'Non spécifié';
            $repasParType[$type] = ($repasParType[$type] ?? 0) + 1;
            $caloriesParType[$type] = ($caloriesParType[$type] ?? 0) + $calories;
        }

        $nombreRepas = count($this->getRepas());
        $moyenneCalories = $nombreRepas > 0 ? round($totalCalories / $nombreRepas) : 0;

        return [
            'total_calories' => $totalCalories,
            'moyenne_calories' => $moyenneCalories,
            'nombre_repas' => $nombreRepas,
            'repas_par_type' => $repasParType,
            'calories_par_type' => $caloriesParType,
        ];
    }

    public function isActif(): bool
    {
        if (!$this->dateFin) return false;
        $now = new \DateTime();
        return $this->dateFin > $now;
    }

    public function getJoursRestants(): int
    {
        if (!$this->dateFin) return 0;

        $now = new \DateTime();
        if ($this->dateFin < $now) return 0;

        $interval = $now->diff($this->dateFin);
        return $interval->days;
    }

    public function getDatesFormatees(): string
    {
        if (!$this->dateDebut || !$this->dateFin) return 'Dates non définies';
        return $this->dateDebut->format('d/m/Y') . ' - ' . $this->dateFin->format('d/m/Y');
    }

    public function getResume(): string
    {
        $resume = $this->objectif;
        if ($this->description) {
            $resume .= ' : ' . substr($this->description, 0, 100) . '...';
        }
        return $resume;
    }

    public function __toString(): string
    {
        return $this->objectif . ' (' . $this->getUser()?->getEmail() . ')';
    }
}
