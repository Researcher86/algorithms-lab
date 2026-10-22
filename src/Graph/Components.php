<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use InvalidArgumentException;

/**
 * Connected components of an undirected graph.
 *
 * A directed graph is refused rather than silently interpreted as if every
 * edge were bidirectional; weak and strong components answer different
 * questions, and the caller should choose one explicitly.
 */
final class Components
{
    /**
     * @return list<list<int>>
     */
    public static function undirected(Graph $graph): array
    {
        if ($graph->directed()) {
            throw new InvalidArgumentException('Components require an undirected graph');
        }

        $seen = array_fill(0, $graph->vertexCount(), false);
        $components = [];

        for ($vertex = 0; $vertex < $graph->vertexCount(); ++$vertex) {
            if ($seen[$vertex]) {
                continue;
            }

            $result = Bfs::run($graph, $vertex);
            $component = [];

            foreach ($result->order as $reached) {
                $seen[$reached] = true;
                $component[] = $reached;
            }

            sort($component);
            $components[] = $component;
        }

        return $components;
    }
}
