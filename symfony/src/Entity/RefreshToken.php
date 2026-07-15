<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128, unique: true)]
    private string $token;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $expiresAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(User $user, string $token, \DateTime $expiresAt)
    {
        $this->user      = $user;
        $this->token     = $token;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getToken(): string { return $this->token; }

    public function getUser(): User { return $this->user; }

    public function getExpiresAt(): \DateTime { return $this->expiresAt; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTime();
    }
}
