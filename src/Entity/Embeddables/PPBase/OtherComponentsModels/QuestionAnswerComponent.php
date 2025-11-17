<?php

namespace App\Entity\Embeddables\PPBase\OtherComponentsModels;

use Symfony\Component\Validator\Constraints as Assert;

class QuestionAnswerComponent implements ComponentInterface
{
    // User-provided fields
    #[Assert\NotBlank(groups: ['input'])]
    #[Assert\Length(min: 5, max: 2500, groups: ['input'])]
    #[Assert\Regex(
        pattern: '/[A-Za-zÀ-ÖØ-öø-ÿ0-9]/u',
        message: 'La question doit contenir au moins un caractère lisible.',
        groups: ['input']
    )]
    private string $question;

    #[Assert\NotBlank(groups: ['input'])]
    #[Assert\Length(min: 10, max: 5000, groups: ['input'])]
    #[Assert\Regex(
        pattern: '/[A-Za-zÀ-ÖØ-öø-ÿ0-9]/u',
        message: 'La réponse doit contenir au moins un caractère lisible.',
        groups: ['input']
    )]
    private string $answer;

    // Internal fields
    private string $id;
    private int $position;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $question,
        string $answer,
        int $position = 0,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->question = $question;
        $this->answer = $answer;
        $this->position = $position;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt;
    }

    public static function createNew(string $question, string $answer): self
    {
        return new self(
            id: bin2hex(random_bytes(16)),
            question: $question,
            answer: $answer,
            position: 0,
            createdAt: new \DateTimeImmutable(),
            updatedAt: null
        );
    }

    // -------- Interface Implementation --------

    public static function fromArray(array $data): self
    {
        $createdAt = isset($data['createdAt']) && is_string($data['createdAt'])
            ? new \DateTimeImmutable($data['createdAt'])
            : new \DateTimeImmutable();

        $updatedAt = isset($data['updatedAt']) && is_string($data['updatedAt'])
            ? new \DateTimeImmutable($data['updatedAt'])
            : null;

        return new self(
            id: $data['id'],
            question: $data['question'],
            answer: $data['answer'],
            position: $data['position'] ?? 0,
            createdAt: $createdAt,
            updatedAt: $updatedAt
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'position' => $this->position,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }

    // getters / setters

    public function getId(): string { return $this->id; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): void { $this->position = $position; }

    public function setUpdatedAt(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function getQuestion(): string { return $this->question; }
    public function setQuestion(string $question): void { $this->question = $question; }

    public function getAnswer(): string { return $this->answer; }
    public function setAnswer(string $answer): void { $this->answer = $answer; }
}
