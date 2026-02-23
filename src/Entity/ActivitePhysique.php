<?php

namespace App\Entity;

use App\Repository\ActivitePhysiqueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActivitePhysiqueRepository::class)]
class ActivitePhysique
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le nom ne doit pas être vide")]
    #[Assert\Length(min: 3, max: 50, minMessage: "Le nom doit faire entre 3 et 50 caractères")]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(min: 10, minMessage: "La description doit expliquer davantage")]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le type d'Activité est obligatoire")]
    #[Assert\Length(min: 3, max: 50, minMessage: "Le type doit faire au moins {{ limit }} caractères", maxMessage: "Le type ne peut pas dépasser {{ limit }} caractères")]
    private ?string $typeActivite = null;

    #[ORM\Column(length: 255)]
    #[Assert\Choice(choices: ["Débutant", "Intermédiaire", "Avancé"], message: "Niveau invalide")]
    private ?string $niveau = null;

    #[ORM\Column]
    #[Assert\Positive(message: "La durée doit être positive")]
    #[Assert\Range(min: 5, max: 300, notInRangeMessage: "La durée doit être comprise entre {{ min }} et {{ max }} minutes")]
    private ?int $dureeEstimee = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: "Les calories doivent être positives ou nulles")]
    #[Assert\Range(min: 0, max: 2000, notInRangeMessage: "Les calories ne peuvent pas excéder {{ max }}")]
    private ?int $caloriesEstimees = null;

    #[ORM\Column]
    private ?bool $actif = null;

    #[ORM\ManyToOne(inversedBy: 'activitePhysiques')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ObjectifSportif $objectifSportif = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTypeActivite(): ?string
    {
        return $this->typeActivite;
    }

    public function setTypeActivite(string $typeActivite): static
    {
        $this->typeActivite = $typeActivite;

        return $this;
    }

    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    public function setNiveau(string $niveau): static
    {
        $this->niveau = $niveau;

        return $this;
    }

    public function getDureeEstimee(): ?int
    {
        return $this->dureeEstimee;
    }

    public function setDureeEstimee(int $dureeEstimee): static
    {
        $this->dureeEstimee = $dureeEstimee;

        return $this;
    }

    public function getCaloriesEstimees(): ?int
    {
        return $this->caloriesEstimees;
    }

    public function setCaloriesEstimees(int $caloriesEstimees): static
    {
        $this->caloriesEstimees = $caloriesEstimees;

        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getObjectifSportif(): ?ObjectifSportif
    {
        return $this->objectifSportif;
    }

    public function setObjectifSportif(?ObjectifSportif $objectifSportif): static
    {
        $this->objectifSportif = $objectifSportif;

        return $this;
    }
}
