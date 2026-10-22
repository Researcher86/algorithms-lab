<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

/**
 * A graph that can be built incrementally.
 *
 * CsrGraph is intentionally read-only: it is built from an edge list once, and
 * pretending it supports O(1) inserts would hide the cost that makes it useful.
 */
interface MutableGraph extends Graph
{
    public function addEdge(int $from, int $to, float $weight = 1.0): void;
}
