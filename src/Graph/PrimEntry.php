<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

/**
 * One candidate edge in Prim's heap.
 */
final readonly class PrimEntry
{
    public function __construct(
        public float $weight,
        public int $from,
        public int $to,
    ) {
    }
}
