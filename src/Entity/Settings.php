<?php

namespace App\Entity;

use App\Repository\SettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Table(name: 'settings')]
class Settings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: 'Keywords are required')]
    private ?string $keywords = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Type(type: 'integer', message: 'Min proposals must be a number')]
    #[Assert\PositiveOrZero(message: 'Min proposals must be positive or zero')]
    private ?int $minProposals = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Type(type: 'integer', message: 'Max proposals must be a number')]
    #[Assert\PositiveOrZero(message: 'Max proposals must be positive or zero')]
    private ?int $maxProposals = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $excludedCountries = null;

    #[ORM\Column]
    private bool $emailNotifications = true;

    #[ORM\Column]
    private bool $telegramNotifications = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'Please enter a valid email address')]
    private ?string $emailAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telegramChatId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): static
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function getMinProposals(): ?int
    {
        return $this->minProposals;
    }

    public function setMinProposals(?int $minProposals): static
    {
        $this->minProposals = $minProposals;

        return $this;
    }

    public function getMaxProposals(): ?int
    {
        return $this->maxProposals;
    }

    public function setMaxProposals(?int $maxProposals): static
    {
        $this->maxProposals = $maxProposals;

        return $this;
    }

    public function getExcludedCountries(): ?string
    {
        return $this->excludedCountries;
    }

    public function setExcludedCountries(?string $excludedCountries): static
    {
        $this->excludedCountries = $excludedCountries;

        return $this;
    }

    public function isEmailNotifications(): bool
    {
        return $this->emailNotifications;
    }

    public function setEmailNotifications(bool $emailNotifications): static
    {
        $this->emailNotifications = $emailNotifications;

        return $this;
    }

    public function isTelegramNotifications(): bool
    {
        return $this->telegramNotifications;
    }

    public function setTelegramNotifications(bool $telegramNotifications): static
    {
        $this->telegramNotifications = $telegramNotifications;

        return $this;
    }

    public function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    public function setEmailAddress(?string $emailAddress): static
    {
        $this->emailAddress = $emailAddress;

        return $this;
    }

    public function getTelegramChatId(): ?string
    {
        return $this->telegramChatId;
    }

    public function setTelegramChatId(?string $telegramChatId): static
    {
        $this->telegramChatId = $telegramChatId;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function updateTimestamp(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
