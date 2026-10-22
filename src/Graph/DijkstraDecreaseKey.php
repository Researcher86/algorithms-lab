<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use AlgorithmsLab\Foundation\IndexedMinHeap;
use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;
use OutOfRangeException;

/**
 * Dijkstra with an indexed heap and true decrease-key.
 *
 * The heap holds each unsettled vertex at most once. A relaxation lowers its
 * priority in place instead of pushing a second entry, so the heap size is
 * bounded by V. This is the structural contrast to the lazy variant.
 */
final class DijkstraDecreaseKey
{
    public static function run(Graph $graph, int $source, ?Counters $counters = null): ShortestPaths
    {
        self::assertSource($graph, $source);

        foreach ($graph->edges() as $edge) {
            if ($edge->weight < 0.0) {
                throw new InvalidArgumentException('Dijkstra requires non-negative edge weights');
            }
        }

        $distances = array_fill(0, $graph->vertexCount(), INF);
        $parents = array_fill(0, $graph->vertexCount(), null);
        $settled = array_fill(0, $graph->vertexCount(), false);
        $distances[$source] = 0.0;
        $heap = new IndexedMinHeap();
        $heap->insertOrDecrease($source, 0.0);
        $relaxations = 0;
        $heapPops = 0;

        while (!$heap->isEmpty()) {
            $entry = $heap->extractMin();
            ++$heapPops;

            if ($counters !== null) {
                ++$counters->nodeReads;
            }

            $vertex = $entry['vertex'];

            if ($settled[$vertex]) {
                continue;
            }

            $settled[$vertex] = true;

            foreach ($graph->neighbors($vertex) as $neighbor => $weight) {
                $candidate = $distances[$vertex] + $weight;

                if ($candidate >= $distances[$neighbor]) {
                    continue;
                }

                $distances[$neighbor] = $candidate;
                $parents[$neighbor] = $vertex;
                ++$relaxations;
                $heap->insertOrDecrease($neighbor, $candidate);
            }
        }

        return new ShortestPaths($distances, $parents, $relaxations, $heapPops);
    }

    private static function assertSource(Graph $graph, int $source): void
    {
        if ($source < 0 || $source >= $graph->vertexCount()) {
            throw new OutOfRangeException(sprintf('Unknown graph vertex: %d', $source));
        }
    }
}
