<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use AlgorithmsLab\Foundation\BinaryHeap;
use AlgorithmsLab\Foundation\Comparator;
use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;
use LogicException;
use OutOfRangeException;

/**
 * Dijkstra with a custom binary heap and lazy deletion.
 *
 * Every improvement pushes a new heap entry; the old entry stays until it is
 * popped and fails the distance check. This avoids maintaining a position map
 * and is the baseline the decrease-key variant will be compared against.
 */
final class Dijkstra
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
        $heap = new BinaryHeap(Comparator::of(static function (mixed $left, mixed $right): int {
            if (!$left instanceof DijkstraEntry || !$right instanceof DijkstraEntry) {
                throw new LogicException('Dijkstra heap contains a non-entry');
            }

            $byDistance = $left->distance <=> $right->distance;

            return $byDistance !== 0 ? $byDistance : $left->vertex <=> $right->vertex;
        }));
        $heap->insert(new DijkstraEntry($source, 0.0));
        $relaxations = 0;
        $heapPops = 0;

        while (!$heap->isEmpty()) {
            $entry = $heap->extract();
            ++$heapPops;

            if ($counters !== null) {
                ++$counters->nodeReads;
            }

            if (!$entry instanceof DijkstraEntry) {
                throw new LogicException('Dijkstra heap contains a non-entry');
            }

            if ($settled[$entry->vertex]) {
                continue;
            }

            if ($entry->distance > $distances[$entry->vertex]) {
                continue;
            }

            $settled[$entry->vertex] = true;

            foreach ($graph->neighbors($entry->vertex) as $neighbor => $weight) {
                $candidate = $distances[$entry->vertex] + $weight;

                if ($candidate >= $distances[$neighbor]) {
                    continue;
                }

                $distances[$neighbor] = $candidate;
                $parents[$neighbor] = $entry->vertex;
                ++$relaxations;
                $heap->insert(new DijkstraEntry($neighbor, $candidate));
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
