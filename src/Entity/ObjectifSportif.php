<?php

namespace App\Entity;

use App\Repository\ObjectifSportifRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ObjectifSportifRepository::class)]
class ObjectifSportif
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le libellé ne doit pas être vide")]
    #[Assert\Length(min: 3, max: 50, minMessage: "Le libellé doit contenir au moins {{ limit }} caractères", maxMessage: "Le libellé ne peut pas dépasser {{ limit }} caractères")]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(min: 10, minMessage: "La description doit contenir au moins {{ limit }} caractères")]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le type d'objectif est obligatoire")]
    #[Assert\Length(min: 3, max: 30, minMessage: "Le type doit avoir au moins {{ limit }} caractères", maxMessage: "Le type ne peut pas dépasser {{ limit }} caractères")]
    private ?string $typeObjectif = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: "La date de début est obligatoire")]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\GreaterThan(propertyPath: "dateDebut", message: "La date de fin doit être postérieure à la date de début")]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ["En cours", "Atteint", "Abandonné"], message: "Statut invalide")]
    private ?string $statut = null;

    #[ORM\ManyToOne(inversedBy: 'objectifSportifs')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'objectifSportif', targetEntity: ActivitePhysique::class, orphanRemoval: true)]
    private Collection $activitePhysiques;

    public function __construct()
    {
        $this->activitePhysiques = new ArrayCollection();
        $this->dateDebut = new \DateTime(); // Default to today
    }

    // Méthode métier pour évaluer la progression (Ex: nombre d'activités réalisées)
    public function evaluerProgression(): int
    {
        return $this->activitePhysiques->filter(function(ActivitePhysique $activite) {
            return $activite->isActif();
        })->count();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

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

    public function getTypeObjectif(): ?string
    {
        return $this->typeObjectif;
    }

    public function setTypeObjectif(string $typeObjectif): static
    {
        $this->typeObjectif = $typeObjectif;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, ActivitePhysique>
     */
    public function getActivitePhysiques(): Collection
    {
        return $this->activitePhysiques;
    }

    public function addActivitePhysique(ActivitePhysique $activitePhysique): static
    {
        if (!$this->activitePhysiques->contains($activitePhysique)) {
            $this->activitePhysiques->add($activitePhysique);
            $activitePhysique->setObjectifSportif($this);
        }

        return $this;
    }

    public function removeActivitePhysique(ActivitePhysique $activitePhysique): static
    {
        if ($this->activitePhysiques->removeElement($activitePhysique)) {
            // set the owning side to null (unless already changed)
            if ($activitePhysique->getObjectifSportif() === $this) {
                $activitePhysique->setObjectifSportif(null);
            }
        }

        return $this;
    }
}
