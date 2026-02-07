<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    // ✅ On mappe "password" sur la colonne EXISTANTE "mot_de_passe"
    #[ORM\Column(name: 'mot_de_passe', length: 255)]
    private ?string $password = null;

    // ✅ On ajoute roles (il faut qu’elle existe en DB => Solution A2 ci-dessous)
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    /**
     * @var Collection<int, PlanNutrition>
     */
    #[ORM\OneToMany(targetEntity: PlanNutrition::class, mappedBy: 'user')]
    private Collection $planNutritions;

    public function __construct()
    {
        $this->planNutritions = new ArrayCollection();
        $this->roles = ['ROLE_USER'];
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $hashedPassword): static
    {
        $this->password = $hashedPassword;
        return $this;
    }

    // Compat si ton code appelle encore motDePasse
    public function getMotDePasse(): ?string { return $this->password; }
    public function setMotDePasse(string $motDePasse): static { $this->password = $motDePasse; return $this; }

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

    public function __toString(): string
    {
        return trim(($this->nom ?? '') . ' (' . ($this->email ?? '') . ')');
    }
}
