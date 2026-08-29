<?php

namespace App\Entity;

use App\Repository\QuizScoreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuizScoreRepository::class)]
class QuizScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'quizScores')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column]
    private ?int $score = null;

    #[ORM\Column]
    private ?int $rounds = null;

    #[ORM\Column]
    private ?int $clipLength = null;

    #[ORM\Column(length: 20)]
    private ?string $guessMode = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $playedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getRounds(): ?int
    {
        return $this->rounds;
    }

    public function setRounds(int $rounds): static
    {
        $this->rounds = $rounds;

        return $this;
    }

    public function getClipLength(): ?int
    {
        return $this->clipLength;
    }

    public function setClipLength(int $clipLength): static
    {
        $this->clipLength = $clipLength;

        return $this;
    }

    public function getGuessMode(): ?string
    {
        return $this->guessMode;
    }

    public function setGuessMode(string $guessMode): static
    {
        $this->guessMode = $guessMode;

        return $this;
    }

    public function getPlayedAt(): ?\DateTimeImmutable
    {
        return $this->playedAt;
    }

    public function setPlayedAt(\DateTimeImmutable $playedAt): static
    {
        $this->playedAt = $playedAt;

        return $this;
    }
}
