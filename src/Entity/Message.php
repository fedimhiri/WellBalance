<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La conversation est obligatoire.')]
    private ?Conversation $conversation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'expéditeur est obligatoire.')]
    private ?User $sender = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 10000, maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachment = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aiAnalysis = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function getSender(): ?User
    {
        return $this->sender;
    }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getAttachment(): ?string
    {
        return $this->attachment;
    }

    public function setAttachment(?string $attachment): static
    {
        $this->attachment = $attachment;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getAiAnalysis(): ?array
    {
        return $this->aiAnalysis;
    }

    public function setAiAnalysis(?array $aiAnalysis): self
    {
        $this->aiAnalysis = $aiAnalysis;
        return $this;
    }

    public function hasSuccessfulAnalysis(): bool
    {
        return $this->aiAnalysis !== null && 
               isset($this->aiAnalysis['success']) && 
               $this->aiAnalysis['success'] === true;
    }

    // Méthodes pour déterminer le type d'expéditeur
    public function isFromDoctor(): bool
    {
        return $this->sender && in_array('ROLE_ADMIN', $this->sender->getRoles());
    }

    public function isFromUser(): bool
    {
        return $this->sender && !in_array('ROLE_ADMIN', $this->sender->getRoles());
    }

    // Méthodes de compatibilité avec l'ancien code
    public function getSenderType(): string
    {
        return $this->isFromDoctor() ? 'doctor' : 'user';
    }

    public function getSenderId(): int
    {
        return $this->sender ? $this->sender->getId() : 0;
    }
}