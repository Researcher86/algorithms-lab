<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use AlgorithmsLab\Foundation\BinaryHeap;
use AlgorithmsLab\Foundation\Comparator;
use InvalidArgumentException;
use LogicException;
use OutOfRangeException;

/**
 * Prim's algorithm from one start vertex.
 *
 * The heap holds candidate edges; an entry whose target is already in the tree
 * is discarded lazily. This returns the tree of the start vertex's component,
 * not a forest for a disconnected graph.
 */
final class Prim
{
    public static function run(Graph $graph, int $start = 0): MstResult
    {
        if ($graph->directed()) {
            throw new InvalidArgumentException('Prim requires an undirected graph');
        }

        if ($start < 0 || $start >= $graph->vertexCount()) {
            throw new OutOfRangeException(sprintf('Unknown graph vertex: %d', $start));
        }

        $visited = array_fill(0, $graph->vertexCount(), false);
        $visited[$start] = true;
        $heap = new BinaryHeap(Comparator::of(static function (mixed $left, mixed $right): int {
            if (!$left instanceof PrimEntry || !$right instanceof PrimEntry) {
                throw new LogicException('Prim heap contains a non-entry');
            }

            $byWeight = $left->weight <=> $right->weight;

            if ($byWeight !== 0) {
                return $byWeight;
            }

            $byTo = $left->to <=> $right->to;

            return $byTo !== 0 ? $byTo : $left->from <=> $right->from;
        }));
        self::pushNeighbors($graph, $start, $heap);
        $mst = [];
        $weight = 0.0;

        while (!$heap->isEmpty()) {
            $entry = $heap->extract();

            if (!$entry instanceof PrimEntry) {
                throw new LogicException('Prim heap contains a non-entry');
            }

            if ($visited[$entry->to]) {
                continue;
            }

            $visited[$entry->to] = true;
            $mst[] = new Edge($entry->from, $entry->to, $entry->weight);
            $weight += $entry->weight;
            self::pushNeighbors($graph, $entry->to, $heap);
        }

        return new MstResult($mst, $weight);
    }

    private static function pushNeighbors(Graph $graph, int $vertex, BinaryHeap $heap): void
    {
        foreach ($graph->neighbors($vertex) as $neighbor => $weight) {
            $heap->insert(new PrimEntry($weight, $vertex, $neighbor));
        }
    }
}
