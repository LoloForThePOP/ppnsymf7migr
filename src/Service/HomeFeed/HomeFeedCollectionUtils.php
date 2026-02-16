<?php

namespace App\Service\HomeFeed;

use App\Entity\PPBase;

final class HomeFeedCollectionUtils
{
    /**
     * @param array<int,mixed> $items
     *
     * @return array<int,mixed>
     */
    public static function shuffleTopWindow(
        array $items,
        int $cardsPerBlock,
        int $windowMultiplier,
        int $windowMin
    ): array {
        if (count($items) <= $cardsPerBlock) {
            return $items;
        }

        $window = min(
            count($items),
            max($windowMin, $cardsPerBlock * $windowMultiplier)
        );
        $top = array_slice($items, 0, $window);
        shuffle($top);

        return array_merge($top, array_slice($items, $window));
    }

    /**
     * @param array<int,mixed> ...$batches
     *
     * @return PPBase[]
     */
    public static function mergeUniquePresentations(array ...$batches): array
    {
        $merged = [];
        $seen = [];

        foreach ($batches as $batch) {
            foreach ($batch as $item) {
                if (!$item instanceof PPBase) {
                    continue;
                }

                $id = $item->getId();
                if ($id === null || isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $merged[] = $item;
            }
        }

        return $merged;
    }
}

