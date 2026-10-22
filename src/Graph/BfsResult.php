<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

/**
 * The result of one breadth-first traversal from a single source.
 *
 * Distances are edge counts; unreachable vertices keep distance -1. Parents
 * form a tree over the reached component and are null for the source and for
 * unreachable vertices.
 */
final readonly class BfsResult
{
    /**
     * @param list<int> $order
     * @param array<int, int> $distances
     * @param array<int, int|null> $parents
     */
    public function __construct(
        public array $order,
        public array $distances,
        public array $parents,
    ) {
    }

    public function visited(int $vertex): bool
    {
        return ($this->distances[$vertex] ?? -1) >= 0;
    }

    public function maxDistance(): int
    {
        $maximum = 0;

        foreach ($this->distances as $distance) {
            if ($distance > $maximum) {
                $maximum = $distance;
            }
        }

        return $maximum;
    }
}
