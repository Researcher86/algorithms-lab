<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

/**
 * A minimum spanning tree or forest and its total weight.
 */
final readonly class MstResult
{
    /**
     * @param list<Edge> $edges
     */
    public function __construct(
        public array $edges,
        public float $weight,
    ) {
    }
}
