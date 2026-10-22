<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use AlgorithmsLab\Instrumentation\Counters;
use OutOfRangeException;

/**
 * Iterative depth-first search with enter and exit markers.
 *
 * The exit marker makes postorder explicit without recursion, which matters
 * for graphs deep enough to exhaust the PHP stack. Children are pushed in
 * reverse so the traversal visits them in the same order a recursive DFS
 * would.
 */
final class Dfs
{
    public static function run(Graph $graph, int $source, ?Counters $counters = null): DfsResult
    {
        self::assertSource($graph, $source);
        $visited = array_fill(0, $graph->vertexCount(), false);
        $parents = array_fill(0, $graph->vertexCount(), null);
        $order = [];
        $postorder = [];
        /** @var list<array{vertex: int, parent: int|null, exit: bool}> $stack */
        $stack = [['vertex' => $source, 'parent' => null, 'exit' => false]];

        while ($stack !== []) {
            $frame = array_pop($stack);

            if ($frame['exit']) {
                $postorder[] = $frame['vertex'];

                continue;
            }

            $vertex = $frame['vertex'];

            if ($visited[$vertex]) {
                continue;
            }

            $visited[$vertex] = true;
            $parents[$vertex] = $frame['parent'];
            $order[] = $vertex;
            $stack[] = ['vertex' => $vertex, 'parent' => $frame['parent'], 'exit' => true];
            $targets = array_keys($graph->neighbors($vertex));

            for ($index = count($targets) - 1; $index >= 0; --$index) {
                $neighbor = $targets[$index];

                if ($counters !== null) {
                    ++$counters->nodeReads;
                }

                if (!$visited[$neighbor]) {
                    $stack[] = ['vertex' => $neighbor, 'parent' => $vertex, 'exit' => false];
                }
            }
        }

        return new DfsResult($order, $postorder, $parents);
    }

    private static function assertSource(Graph $graph, int $source): void
    {
        if ($source < 0 || $source >= $graph->vertexCount()) {
            throw new OutOfRangeException(sprintf('Unknown graph vertex: %d', $source));
        }
    }
}
