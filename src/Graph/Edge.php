<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

/**
 * One weighted edge.
 *
 * Undirected graphs store a single Edge for a pair; representations that need
 * both directions expand it when they are built. Weights are floats so an
 * integer input does not silently pick a different algorithm later.
 */
final readonly class Edge
{
    public function __construct(
        public int $from,
        public int $to,
        public float $weight = 1.0,
    ) {
    }
}
