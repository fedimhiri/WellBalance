<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé')]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d’utilisateur est déjà utilisé')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L’email est obligatoire')]
    #[Assert\Email(message: 'Format email invalide')]
    private ?string $email = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Le nom d’utilisateur est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Le nom d’utilisateur doit contenir au moins {{ limit }} caractères'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9._-]+$/',
        message: 'Le username ne doit contenir que des lettres et chiffres'
    )]
    private ?string $username = null;

    // Champs ajouté pour compatibility Tache_Youssef
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 8)]
    #[Assert\NotBlank(message: 'Le téléphone est obligatoire')]
    #[Assert\Regex(
        pattern: '/^[0-9]{8}$/',
        message: 'Le numéro doit contenir exactement 8 chiffres'
    )]
    private ?string $telephone = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\OneToMany(targetEntity: PlanNutrition::class, mappedBy: 'user')]
    private Collection $planNutritions;

    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'user')]
    private Collection $documents;

    public function __construct()
    {
        $this->planNutritions = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->roles = ['ROLE_USER'];
    }

    // =================== GETTERS / SETTERS ===================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return array_unique(array_merge($this->roles, ['ROLE_USER']));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    // Alias pour compatibilité Tache_Youssef
    public function getMotDePasse(): ?string
    {
        return $this->password;
    }

    public function setMotDePasse(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void {}

    /**
     * @return Collection<int, PlanNutrition>
     */
    public function getPlanNutritions(): Collection
    {
        return $this->planNutritions;
    }

    public function addPlanNutrition(PlanNutrition $planNutrition): static
    {
        if (!$this->planNutritions->contains($planNutrition)) {
            $this->planNutritions->add($planNutrition);
            $planNutrition->setUser($this);
        }
        return $this;
    }

    public function removePlanNutrition(PlanNutrition $planNutrition): static
    {
        if ($this->planNutritions->removeElement($planNutrition)) {
            if ($planNutrition->getUser() === $this) {
                $planNutrition->setUser(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setUser($this);
        }
        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document) && $document->getUser() === $this) {
            $document->setUser(null);
        }
        return $this;
    }
}
