<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use InvalidArgumentException;

/**
 * Two topological sorts for a directed graph.
 *
 * Kahn's algorithm is the queue/indegree version; the DFS version uses colors
 * and reverse postorder. Both return null when a cycle makes a total order
 * impossible. Neither result is unique; the experiment checks validity, not
 * equality with the other implementation.
 */
final class TopologicalSort
{
    /**
     * @return list<int>|null
     */
    public static function kahn(Graph $graph): ?array
    {
        self::assertDirected($graph);
        $indegree = array_fill(0, $graph->vertexCount(), 0);

        foreach ($graph->edges() as $edge) {
            ++$indegree[$edge->to];
        }

        $queue = [];

        foreach ($indegree as $vertex => $degree) {
            if ($degree === 0) {
                $queue[] = $vertex;
            }
        }

        $head = 0;
        $order = [];

        while ($head < count($queue)) {
            $vertex = $queue[$head];
            ++$head;
            $order[] = $vertex;

            foreach ($graph->neighbors($vertex) as $neighbor => $weight) {
                --$indegree[$neighbor];

                if ($indegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        return count($order) === $graph->vertexCount() ? $order : null;
    }

    /**
     * @return list<int>|null
     */
    public static function dfs(Graph $graph): ?array
    {
        self::assertDirected($graph);
        $state = array_fill(0, $graph->vertexCount(), 0);
        $postorder = [];

        for ($start = 0; $start < $graph->vertexCount(); ++$start) {
            if ($state[$start] !== 0) {
                continue;
            }

            /** @var list<array{vertex: int, exit: bool}> $stack */
            $stack = [['vertex' => $start, 'exit' => false]];

            while ($stack !== []) {
                $frame = array_pop($stack);
                $vertex = $frame['vertex'];

                if ($frame['exit']) {
                    $state[$vertex] = 2;
                    $postorder[] = $vertex;

                    continue;
                }

                if ($state[$vertex] === 1) {
                    return null;
                }

                if ($state[$vertex] === 2) {
                    continue;
                }

                $state[$vertex] = 1;
                $stack[] = ['vertex' => $vertex, 'exit' => true];
                $neighbors = array_keys($graph->neighbors($vertex));

                for ($index = count($neighbors) - 1; $index >= 0; --$index) {
                    $neighbor = $neighbors[$index];

                    if ($state[$neighbor] === 1) {
                        return null;
                    }

                    if ($state[$neighbor] === 0) {
                        $stack[] = ['vertex' => $neighbor, 'exit' => false];
                    }
                }
            }
        }

        return array_reverse($postorder);
    }

    private static function assertDirected(Graph $graph): void
    {
        if (!$graph->directed()) {
            throw new InvalidArgumentException('Topological sort requires a directed graph');
        }
    }
}
