<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\Need;
use App\Entity\News;
use App\Entity\PPBase;
use App\Entity\Place;
use App\Entity\Slide;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Centralises reorder/delete mutations triggered from the live editor so
 * controllers and templates do not have to duplicate switch/case logic.
 */
class ProjectPresentationStructureService
{
    /**
     * Doctrine-managed collections indexed by their scope keyword.
     *
     * @var array<string, array{class: class-string, getter: string}>
     */
    private const COLLECTION_SCOPES = [
        'documents' => [
            'class' => Document::class,
            'getter' => 'getDocuments',
        ],
        'needs' => [
            'class' => Need::class,
            'getter' => 'getNeeds',
        ],
        'slides' => [
            'class' => Slide::class,
            'getter' => 'getSlides',
        ],
        'places' => [
            'class' => Place::class,
            'getter' => 'getPlaces',
        ],
        'news' => [
            'class' => News::class,
            'getter' => 'getNews',
        ],
    ];

    /**
     * Map public scope keywords to OtherComponents storage keys.
     */
    private const OTHER_COMPONENT_SCOPES = [
        'websites' => 'websites',
        'questionsAnswers' => 'questions_answers',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheThumbnailService $cacheThumbnail,
        private readonly AssessPPScoreService $scoreService,
    ) {
    }

    /**
     * Reorder component items according to the ordered ids sent by the frontend.
     *
     * @param string[] $orderedIds
     */
    public function reorder(PPBase $presentation, string $scope, array $orderedIds): void
    {
        $normalizedIds = $this->normalizeIds($orderedIds);
        if ($normalizedIds === []) {
            throw new \InvalidArgumentException('Aucun élément à ordonner.');
        }

        if ($this->isOtherComponentScope($scope)) {
            $this->reorderOtherComponent($presentation, $scope, $normalizedIds);
        } else {
            $this->reorderDoctrineCollection($presentation, $scope, $normalizedIds);
        }

        $this->em->flush();
        $this->afterStructureChange($scope, $presentation);
    }

    /**
     * Delete an item (Doctrine entity or JSON component) from the presentation.
     */
    public function delete(PPBase $presentation, string $scope, string $itemId): void
    {
        if ($this->isOtherComponentScope($scope)) {
            $this->deleteOtherComponent($presentation, $scope, $itemId);
        } else {
            $this->deleteDoctrineEntity($presentation, $scope, $itemId);
        }

        $this->em->flush();
        $this->afterStructureChange($scope, $presentation);
    }

    /**
     * @param string[] $orderedIds
     */
    private function reorderDoctrineCollection(PPBase $presentation, string $scope, array $orderedIds): void
    {
        $config = self::COLLECTION_SCOPES[$scope] ?? null;
        if ($config === null) {
            throw new \InvalidArgumentException('Composant inconnu.');
        }

        $getter = $config['getter'];
        /** @var Collection<int, object> $collection */
        $collection = $presentation->$getter();
        $currentIds = array_map(static fn ($item) => (string) $item->getId(), $collection->toArray());

        $this->assertSameElements($currentIds, $orderedIds);

        foreach ($collection as $item) {
            $itemId = (string) $item->getId();
            $position = array_search($itemId, $orderedIds, true);
            if ($position !== false && method_exists($item, 'setPosition')) {
                $item->setPosition($position);
            }
        }
    }

    /**
     * @param string[] $orderedIds
     */
    private function reorderOtherComponent(PPBase $presentation, string $scope, array $orderedIds): void
    {
        $storageKey = $this->resolveOtherComponentKey($scope);
        $components = $presentation->getOtherComponents()->getComponents($storageKey);
        $currentIds = array_map(static fn ($component) => (string) $component->getId(), $components);

        $this->assertSameElements($currentIds, $orderedIds);

        $presentation->getOtherComponents()->reorderComponents($storageKey, $orderedIds);
    }

    private function deleteDoctrineEntity(PPBase $presentation, string $scope, string $itemId): void
    {
        $config = self::COLLECTION_SCOPES[$scope] ?? null;
        if ($config === null) {
            throw new \InvalidArgumentException('Composant inconnu.');
        }

        $id = filter_var($itemId, FILTER_VALIDATE_INT);
        if ($id === false) {
            throw new \InvalidArgumentException('Identifiant invalide.');
        }

        $entity = $this->em->getRepository($config['class'])->find($id);
        if ($entity === null || !$this->belongsToPresentation($entity, $presentation)) {
            throw new \RuntimeException('Élément introuvable.');
        }

        $this->em->remove($entity);
    }

    private function deleteOtherComponent(PPBase $presentation, string $scope, string $itemId): void
    {
        $storageKey = $this->resolveOtherComponentKey($scope);
        $removed = $presentation->getOtherComponents()->removeComponent($storageKey, $itemId);

        if (!$removed) {
            throw new \RuntimeException('Élément introuvable.');
        }
    }

    private function resolveOtherComponentKey(string $scope): string
    {
        $storageKey = self::OTHER_COMPONENT_SCOPES[$scope] ?? null;
        if ($storageKey === null) {
            throw new \InvalidArgumentException('Composant inconnu.');
        }

        return $storageKey;
    }

    /**
     * @param string[] $ids
     *
     * @return string[]
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_map(static fn ($value) => (string) $value, $ids));
    }

    /**
     * Ensure both lists contain the same values (order does not matter).
     *
     * @param string[] $expected
     * @param string[] $provided
     */
    private function assertSameElements(array $expected, array $provided): void
    {
        sort($expected);
        sort($provided);

        if ($expected !== $provided) {
            throw new \InvalidArgumentException('La liste des éléments est invalide.');
        }
    }

    private function isOtherComponentScope(string $scope): bool
    {
        return array_key_exists($scope, self::OTHER_COMPONENT_SCOPES);
    }

    private function belongsToPresentation(object $entity, PPBase $presentation): bool
    {
        foreach (['getProjectPresentation', 'getProject', 'getPresentation'] as $method) {
            if (method_exists($entity, $method) && $entity->$method() === $presentation) {
                return true;
            }
        }

        return false;
    }

    private function afterStructureChange(string $scope, PPBase $presentation): void
    {
        if ($scope !== 'slides') {
            return;
        }

        $this->cacheThumbnail->updateThumbnail($presentation, true);
        $this->scoreService->scoreUpdate($presentation);
    }
}
