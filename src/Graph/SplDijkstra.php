<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use InvalidArgumentException;
use OutOfRangeException;
use SplPriorityQueue;

/**
 * Dijkstra on PHP's SplPriorityQueue, used only as a baseline.
 *
 * SplPriorityQueue is a max-heap, so the priority is the negated distance.
 * The same lazy-entry trick is used; stale entries are discarded when popped.
 * It is deliberately not the reference implementation: its tie behavior and
 * internal allocation are outside the lab's comparator accounting.
 */
final class SplDijkstra
{
    public static function run(Graph $graph, int $source): ShortestPaths
    {
        if ($source < 0 || $source >= $graph->vertexCount()) {
            throw new OutOfRangeException(sprintf('Unknown graph vertex: %d', $source));
        }

        foreach ($graph->edges() as $edge) {
            if ($edge->weight < 0.0) {
                throw new InvalidArgumentException('Dijkstra requires non-negative edge weights');
            }
        }

        $distances = array_fill(0, $graph->vertexCount(), INF);
        $parents = array_fill(0, $graph->vertexCount(), null);
        $settled = array_fill(0, $graph->vertexCount(), false);
        $distances[$source] = 0.0;
        $queue = new SplPriorityQueue();
        $queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        $queue->insert(new DijkstraEntry($source, 0.0), -0.0);
        $relaxations = 0;
        $heapPops = 0;

        while (!$queue->isEmpty()) {
            $entry = $queue->extract();
            ++$heapPops;

            if (!$entry instanceof DijkstraEntry) {
                throw new \LogicException('SplDijkstra queue contains a non-entry');
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
                $queue->insert(new DijkstraEntry($neighbor, $candidate), -$candidate);
            }
        }

        return new ShortestPaths($distances, $parents, $relaxations, $heapPops);
    }
}
