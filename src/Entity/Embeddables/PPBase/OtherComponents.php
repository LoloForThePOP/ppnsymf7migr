<?php

namespace App\Entity\Embeddables\PPBase;

use Doctrine\ORM\Mapping as ORM;

/**
 * This embeddable handles other components that can describe a Project Presentation.
 * Exemples are websites associated with the project (including social networks); a faq (= collection of questions and answers), business cards, etc. 
 * The pattern is extensible so that component types migth be added in the future.
 */

#[ORM\Embeddable]
class OtherComponents
{

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $otherComponents = [];

    public function getOtherComponents(): array
    {
        return $this->otherComponents ?? [];
    }

    public function setOtherComponents(?array $otherComponents): self
    {
        $this->otherComponents = $otherComponents ?? [];
        return $this;
    }

    public function getOC(string $type): array
    {
        return $this->otherComponents[$type] ?? [];
    }

    public function getOCItem(string $type, string $id): ?array
    {
        foreach ($this->getOC($type) as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }
        return null;
    }

    public function setOCItem(string $type, string $id, array $updatedItem): bool
    {
        foreach ($this->otherComponents[$type] as &$item) {
            if ($item['id'] === $id) {
                $updatedItem['updatedAt'] = new \DateTimeImmutable();
                $item = $updatedItem;
                return true;
            }
        }
        return false;
    }

    public function addOtherComponentItem(string $type, array $item): self
    {
        $item['id'] = bin2hex(random_bytes(16));
        $item['createdAt'] = new \DateTimeImmutable();
        $item['position'] = count($this->getOC($type));

        $this->otherComponents[$type][] = $item;

        return $this;
    }

    public function deleteOtherComponentItem(string $type, string $id): self
    {
        $this->otherComponents[$type] = array_values(
            array_filter(
                $this->getOC($type),
                fn($item) => $item['id'] !== $id
            )
        );

        return $this;
    }

    public function positionOtherComponentItem(string $type, array $order): self
    {
        foreach ($this->otherComponents[$type] as &$item) {
            $item['position'] = array_search($item['id'], $order, true);
        }

        usort(
            $this->otherComponents[$type],
            fn($a, $b) => $a['position'] <=> $b['position']
        );

        return $this;
    }
}
