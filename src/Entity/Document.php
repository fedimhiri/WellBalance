<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
#[ORM\HasLifecycleCallbacks]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_document')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 3, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.')]
    private ?string $titre = null;

    #[ORM\Column(name: 'type_document', length: 50)]
    #[Assert\NotBlank(message: 'Le type de document est obligatoire.')]
    private ?string $typeDocument = null;

    #[ORM\Column(name: 'chemin_fichier', length: 500)]
    private ?string $cheminFichier = null;

    #[ORM\Column(name: 'date_upload', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateUpload = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: 'id_categorie')]
    private ?CategorieDocument $categorie = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->dateUpload = new \DateTimeImmutable();
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

    public function getTypeDocument(): ?string
    {
        return $this->typeDocument;
    }

    public function setTypeDocument(string $typeDocument): static
    {
        $this->typeDocument = $typeDocument;
        return $this;
    }

    public function getCheminFichier(): ?string
    {
        return $this->cheminFichier;
    }

    public function setCheminFichier(string $cheminFichier): static
    {
        $this->cheminFichier = $cheminFichier;
        return $this;
    }

    public function getDateUpload(): ?\DateTimeImmutable
    {
        return $this->dateUpload;
    }

    public function setDateUpload(\DateTimeImmutable $dateUpload): static
    {
        $this->dateUpload = $dateUpload;
        return $this;
    }

    public function getCategorie(): ?CategorieDocument
    {
        return $this->categorie;
    }

    public function setCategorie(?CategorieDocument $categorie): static
    {
        $this->categorie = $categorie;
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
}
