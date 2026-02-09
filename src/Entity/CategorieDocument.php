<?php

namespace App\Entity;

use App\Repository\CategorieDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategorieDocumentRepository::class)]
#[ORM\Table(name: 'categorie_document')]
class CategorieDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_categorie')]
    private ?int $id = null;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(min: 1, max: 500)]
    private ?string $description = null;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'categorie')]
    private Collection $documents;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
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
            $document->setCategorie($this);
        }
        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document) && $document->getCategorie() === $this) {
            $document->setCategorie(null);
        }
        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->description;
    }
}
