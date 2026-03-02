<?php

namespace App\Entity;

use App\Repository\TypeRendezVousRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TypeRendezVousRepository::class)]
#[ORM\Table(name: 'type_rendez_vous')]
#[UniqueEntity(fields: ['nomType'], message: 'Ce type de rendez-vous existe deja.')]
class TypeRendezVous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le nom du type est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le nom du type doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le nom du type ne doit pas depasser {{ limit }} caracteres.'
    )]
    private ?string $nomType = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * @var Collection<int, RendezVous>
     */
    #[ORM\OneToMany(mappedBy: 'typeRendezVous', targetEntity: RendezVous::class)]
    private Collection $rendezVous;

    public function __construct()
    {
        $this->rendezVous = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->nomType;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomType(): ?string
    {
        return $this->nomType;
    }

    public function setNomType(string $nomType): static
    {
        $this->nomType = $nomType;

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

    /**
     * @return Collection<int, RendezVous>
     */
    public function getRendezVous(): Collection
    {
        return $this->rendezVous;
    }
}
