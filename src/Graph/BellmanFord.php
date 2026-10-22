<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use OutOfRangeException;

/**
 * Bellman-Ford: V−1 relaxation passes over all edges plus a negative-cycle
 * check.
 *
 * The implementation keeps the full edge list and relaxes both directions of
 * an undirected edge. It is slower than Dijkstra by design; the chapter uses it
 * as the reference that also handles negative weights.
 */
final class BellmanFord
{
    public static function run(Graph $graph, int $source): ShortestPaths
    {
        self::assertSource($graph, $source);
        $distances = array_fill(0, $graph->vertexCount(), INF);
        $parents = array_fill(0, $graph->vertexCount(), null);
        $distances[$source] = 0.0;
        $relaxations = 0;
        $edges = $graph->edges();
        $passes = max(0, $graph->vertexCount() - 1);

        for ($pass = 0; $pass < $passes; ++$pass) {
            $changed = false;

            foreach ($edges as $edge) {
                if (self::relax($edge->from, $edge->to, $edge->weight, $distances, $parents)) {
                    ++$relaxations;
                    $changed = true;
                }

                if (!$graph->directed() && $edge->from !== $edge->to) {
                    if (self::relax($edge->to, $edge->from, $edge->weight, $distances, $parents)) {
                        ++$relaxations;
                        $changed = true;
                    }
                }
            }

            if (!$changed) {
                break;
            }
        }

        $negativeCycle = false;

        foreach ($edges as $edge) {
            if (self::relax($edge->from, $edge->to, $edge->weight, $distances, $parents)) {
                $negativeCycle = true;
                break;
            }

            if (
                !$graph->directed()
                && $edge->from !== $edge->to
                && self::relax($edge->to, $edge->from, $edge->weight, $distances, $parents)
            ) {
                $negativeCycle = true;
                break;
            }
        }

        return new ShortestPaths($distances, $parents, $relaxations, 0, $negativeCycle);
    }

    /**
     * @param array<int, float> $distances
     * @param array<int, int|null> $parents
     */
    private static function relax(int $from, int $to, float $weight, array &$distances, array &$parents): bool
    {
        if (is_infinite($distances[$from])) {
            return false;
        }

        $candidate = $distances[$from] + $weight;

        if ($candidate >= $distances[$to]) {
            return false;
        }

        $distances[$to] = $candidate;
        $parents[$to] = $from;

        return true;
    }

    private static function assertSource(Graph $graph, int $source): void
    {
        if ($source < 0 || $source >= $graph->vertexCount()) {
            throw new OutOfRangeException(sprintf('Unknown graph vertex: %d', $source));
        }
    }
}
