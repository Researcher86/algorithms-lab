<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use AlgorithmsLab\Instrumentation\Counters;
use OutOfRangeException;

/**
 * Breadth-first search with an explicit queue.
 *
 * The queue is a PHP list plus a head index; `array_shift()` would turn the
 * traversal into O(V²) on a large graph. The result keeps order, distances and
 * parents so later algorithms can reuse one pass.
 */
final class Bfs
{
    public static function run(Graph $graph, int $source, ?Counters $counters = null): BfsResult
    {
        self::assertSource($graph, $source);
        $distances = array_fill(0, $graph->vertexCount(), -1);
        $parents = array_fill(0, $graph->vertexCount(), null);
        $order = [];
        $queue = [$source];
        $head = 0;
        $distances[$source] = 0;

        while ($head < count($queue)) {
            $vertex = $queue[$head];
            ++$head;
            $order[] = $vertex;

            foreach ($graph->neighbors($vertex) as $neighbor => $weight) {
                if ($counters !== null) {
                    ++$counters->nodeReads;
                }

                if ($distances[$neighbor] !== -1) {
                    continue;
                }

                $distances[$neighbor] = $distances[$vertex] + 1;
                $parents[$neighbor] = $vertex;
                $queue[] = $neighbor;
            }
        }

        return new BfsResult($order, $distances, $parents);
    }

    private static function assertSource(Graph $graph, int $source): void
    {
        if ($source < 0 || $source >= $graph->vertexCount()) {
            throw new OutOfRangeException(sprintf('Unknown graph vertex: %d', $source));
        }
    }
}
