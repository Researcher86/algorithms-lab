<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

/**
 * Distances and parents from one shortest-path source.
 *
 * Unreachable vertices keep PHP's INF in `distances`; `distance()` converts
 * that to null for callers that prefer not to know about infinity. Relieving
 * INF in a public array would make accidental arithmetic easier; keeping it
 * explicit makes the sentinel visible.
 */
final readonly class ShortestPaths
{
    /**
     * @param array<int, float> $distances
     * @param array<int, int|null> $parents
     */
    public function __construct(
        public array $distances,
        public array $parents,
        public int $relaxations = 0,
        public int $heapPops = 0,
        public bool $negativeCycle = false,
    ) {
    }

    public function distance(int $vertex): ?float
    {
        $distance = $this->distances[$vertex] ?? INF;

        return is_infinite($distance) ? null : $distance;
    }

    public function reachable(int $vertex): bool
    {
        return !is_infinite($this->distances[$vertex] ?? INF);
    }
}
