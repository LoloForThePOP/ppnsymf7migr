<?php

namespace App\Entity\Embeddables\PPBase;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\ComponentRegistry;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\ComponentInterface;

/**
 * This embeddable handles other components that can describe a Project Presentation.
 * Exemples are websites associated with the project (including social networks); a faq (= collection of questions and answers), business cards, etc. 
 * The pattern is extensible so that component types migth be added in the future.
 */

#[ORM\Embeddable]
class OtherComponents
{

    /**
     * Raw JSON storage. Keyed by component type: 'Website', 'QuestionAnswer', 'BusinessCard', etc.
     *
     * Example:
     * {
     *   "Website": [ {...}, {...} ],
     *   "QuestionAnswer":      [ {...}, {...} ]
     * }
     */
    #[ORM\Column(
        name: 'other_components',  // ⚠ adapt if your real column name differs
        type: 'json',
        nullable: true
    )]
    private ?array $data = [];

    public function getRaw(): array
    {
        return $this->data ?? [];
    }

    public function setRaw(?array $data): self
    {
        $this->data = $data ?? [];
        return $this;
    }

    /**
 * Return all items for component type as objects.
 *
 * @return ComponentInterface[]
 */
public function getComponents(string $type): array
{

    $class = ComponentRegistry::classFor($type);

    if (!$class) {
        throw new \InvalidArgumentException("Unknown component type '$type'.");
    }


    $items = $this->data[$type] ?? [];
    return array_map(fn($item) => $class::fromArray($item), $items);
}



public function addComponent(string $type, ComponentInterface $component): self
{

    $class = ComponentRegistry::classFor($type);

    if (!$class) {
        throw new \InvalidArgumentException("Unknown component type '$type'.");
    }

    if (!isset($this->data[$type])) {
        $this->data[$type] = [];
    }

    // AUTO-ASSIGN POSITION
    $nextPosition = count($this->data[$type]);
    $component->setPosition($nextPosition);

    $this->data[$type][] = $component->toArray();

    return $this;
}

    public function updateComponent(string $type, ComponentInterface $updated): bool
    {

        $class = ComponentRegistry::classFor($type);

        if (!$class) {
            throw new \InvalidArgumentException("Unknown component type '$type'.");
        }

        if (!isset($this->data[$type])) {
            return false;
        }

        foreach ($this->data[$type] as &$item) {
            if ($item['id'] === $updated->getId()) {
                $updated->setUpdatedAt();
                $item = $updated->toArray();
                return true;
            }
        }

        return false;
    }

    public function removeComponent(string $type, string $id): bool
    {
        $class = ComponentRegistry::classFor($type);

        if (!$class) {
            throw new \InvalidArgumentException("Unknown component type '$type'.");
        }

        if (!isset($this->data[$type])) {
            return false;
        }

        $before = count($this->data[$type]);

        $this->data[$type] = array_values(
            array_filter(
                $this->data[$type],
                fn($item) => $item['id'] !== $id
            )
        );

        return count($this->data[$type]) !== $before;
    }


    /**
     * Reorders items of a given type based on an array of ids.
     *
     * @param string   $type       e.g. 'websites'
     * @param string[] $orderedIds ids in the desired order
     */
    public function reorderComponents(string $type, array $orderedIds): self
    {
        $class = ComponentRegistry::classFor($type);

        if (!$class) {
            throw new \InvalidArgumentException("Unknown component type '$type'.");
        }

        $components = $this->getComponents($type);
        if (!$components) {
            return $this;
        }

        foreach ($components as $component) {
            $position = array_search($component->getId(), $orderedIds, true);
            if ($position !== false) {
                $component->setPosition($position);
            }
        }

        usort($components, fn($a, $b) => $a->getPosition() <=> $b->getPosition());

        $this->data[$type] = array_map(
            fn($c) => $c->toArray(),
            $components
        );

        return $this;
    }

    public function getItem(string $type, string $id): ?array
    {
        foreach ($this->data[$type] ?? [] as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    public function replaceItem(string $type, string $id, array $payload): bool
    {
        if (!isset($this->data[$type])) {
            return false;
        }

        foreach ($this->data[$type] as $index => $item) {
            if (($item['id'] ?? null) === $id) {
                $payload['id'] = $id;
                $this->data[$type][$index] = array_merge($item, $payload);
                return true;
            }
        }

        return false;
    }

}
