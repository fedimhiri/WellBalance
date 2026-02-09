<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
class RendezVous
{
    public const STATUT_PLANIFIE = 'PLANIFIE';
    public const STATUT_TERMINE  = 'TERMINE';
    public const STATUT_ANNULE   = 'ANNULE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 2, max: 150, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.')]
    private ?string $titre = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull(message: 'La date et l\'heure du rendez-vous sont obligatoires.')]
    private ?\DateTimeInterface $dateRdv = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $lieu = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $notes = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::STATUT_PLANIFIE, self::STATUT_TERMINE, self::STATUT_ANNULE], message: 'Statut invalide.')]
    private string $statut = self::STATUT_PLANIFIE;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Veuillez sélectionner un type de rendez-vous.')]
    private ?TypeRendezVous $type = null;

    public function getId(): ?int { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getDateRdv(): ?\DateTimeInterface { return $this->dateRdv; }
    public function setDateRdv(\DateTimeInterface $dateRdv): static { $this->dateRdv = $dateRdv; return $this; }

    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(?string $lieu): static { $this->lieu = $lieu; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getType(): ?TypeRendezVous { return $this->type; }
    public function setType(?TypeRendezVous $type): static { $this->type = $type; return $this; }
}
