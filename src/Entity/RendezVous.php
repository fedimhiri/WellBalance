<?php

namespace App\Entity;

use App\Repository\RendezVousRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RendezVousRepository::class)]
#[ORM\Table(name: 'rendez_vous')]
class RendezVous
{
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_ACCEPTE = 'ACCEPTE';
    public const STATUT_REFUSE = 'REFUSE';

    public const STATUTS = [
        self::STATUT_EN_COURS,
        self::STATUT_ACCEPTE,
        self::STATUT_REFUSE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 120,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le titre ne doit pas depasser {{ limit }} caracteres.'
    )]
    private ?string $titre = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 2000,
        minMessage: 'La description doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'La description ne doit pas depasser {{ limit }} caracteres.'
    )]
    private ?string $description = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull(message: 'La date du rendez-vous est obligatoire.')]
    #[Assert\GreaterThanOrEqual('today', message: 'La date du rendez-vous ne peut pas etre dans le passe.')]
    private ?\DateTimeImmutable $dateRdv = null;

    #[ORM\Column(type: 'time_immutable')]
    #[Assert\NotNull(message: 'L\'heure du rendez-vous est obligatoire.')]
    private ?\DateTimeImmutable $heureRdv = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUT_EN_COURS])]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(choices: self::STATUTS, message: 'Le statut est invalide.')]
    private string $statut = self::STATUT_EN_COURS;

    #[ORM\ManyToOne(inversedBy: 'rendezVous')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le type de rendez-vous est obligatoire.')]
    private ?TypeRendezVous $typeRendezVous = null;

    #[ORM\ManyToOne(inversedBy: 'patientRendezVous')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $patient = null;

    #[ORM\ManyToOne(inversedBy: 'medecinRendezVous')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Assert\NotNull(message: 'Le medecin est obligatoire.')]
    private ?User $medecin = null;

    public function __construct()
    {
        $this->statut = self::STATUT_EN_COURS;
    }

    public static function getStatutChoices(): array
    {
        return [
            'En cours' => self::STATUT_EN_COURS,
            'Accepte' => self::STATUT_ACCEPTE,
            'Refuse' => self::STATUT_REFUSE,
        ];
    }

    public function canAccept(): bool
    {
        return self::STATUT_EN_COURS === $this->statut;
    }

    public function canReject(): bool
    {
        return self::STATUT_EN_COURS === $this->statut;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

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

    public function getDateRdv(): ?\DateTimeImmutable
    {
        return $this->dateRdv;
    }

    public function setDateRdv(\DateTimeImmutable $dateRdv): static
    {
        $this->dateRdv = $dateRdv;

        return $this;
    }

    public function getHeureRdv(): ?\DateTimeImmutable
    {
        return $this->heureRdv;
    }

    public function setHeureRdv(\DateTimeImmutable $heureRdv): static
    {
        $this->heureRdv = $heureRdv;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        if (!in_array($statut, self::STATUTS, true)) {
            throw new \InvalidArgumentException(sprintf('Statut invalide: %s', $statut));
        }

        $this->statut = $statut;

        return $this;
    }

    public function getTypeRendezVous(): ?TypeRendezVous
    {
        return $this->typeRendezVous;
    }

    public function setTypeRendezVous(?TypeRendezVous $typeRendezVous): static
    {
        $this->typeRendezVous = $typeRendezVous;

        return $this;
    }

    public function getPatient(): ?User
    {
        return $this->patient;
    }

    public function setPatient(?User $patient): static
    {
        $this->patient = $patient;

        return $this;
    }

    public function getMedecin(): ?User
    {
        return $this->medecin;
    }

    public function setMedecin(?User $medecin): static
    {
        $this->medecin = $medecin;

        return $this;
    }
}
