<?php 

namespace App\Entity\Embeddables\PPBase\OtherComponentsModels;

interface ComponentInterface
{
    public static function fromArray(array $data): self;
    public function toArray(): array;

    public function getId(): string;

    public function getPosition(): int;
    public function setPosition(int $position): void;

    public function setUpdatedAt(): void;
}
