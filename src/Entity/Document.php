<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
#[ORM\Index(columns: ['date_upload'], name: 'idx_document_date_upload')]
#[ORM\Index(columns: ['type_document'], name: 'idx_document_type')]
#[ORM\Index(columns: ['type_detecte'], name: 'idx_document_type_detecte')]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(max: 255)]
    private ?string $titre = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le type de document est obligatoire')]
    #[Assert\Length(max: 100)]
    private ?string $typeDocument = null;

    #[ORM\Column(name: 'chemin_fichier', length: 500)]
    private ?string $cheminFichier = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateUpload = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resumeAi = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $motsCles = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $typeDetecte = null;

    #[ORM\Column(name: 'insurance_reference', length: 255, nullable: true)]
    private ?string $insuranceReference = null;

    #[ORM\ManyToOne(targetEntity: CategorieDocument::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(name: 'categorie_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?CategorieDocument $categorie = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    public function __construct()
    {
        $this->dateUpload = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->titre ?? '';
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

    public function getResumeAi(): ?string
    {
        return $this->resumeAi;
    }

    public function setResumeAi(?string $resumeAi): static
    {
        $this->resumeAi = $resumeAi;
        return $this;
    }

    public function getMotsCles(): ?array
    {
        return $this->motsCles;
    }

    public function setMotsCles(?array $motsCles): static
    {
        $this->motsCles = $motsCles;
        return $this;
    }

    public function getTypeDetecte(): ?string
    {
        return $this->typeDetecte;
    }

    public function setTypeDetecte(?string $typeDetecte): static
    {
        $this->typeDetecte = $typeDetecte;
        return $this;
    }

    public function getInsuranceReference(): ?string
    {
        return $this->insuranceReference;
    }

    public function setInsuranceReference(?string $insuranceReference): static
    {
        $this->insuranceReference = $insuranceReference;
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
