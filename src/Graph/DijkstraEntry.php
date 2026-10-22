<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

/**
 * One heap entry for Dijkstra's lazy-deletion variant.
 */
final readonly class DijkstraEntry
{
    public function __construct(
        public int $vertex,
        public float $distance,
    ) {
    }
}
