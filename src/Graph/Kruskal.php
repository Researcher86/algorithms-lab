<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use AlgorithmsLab\Foundation\UnionFind;
use InvalidArgumentException;

/**
 * Kruskal's algorithm: sort edges, then union their endpoints.
 *
 * The sort is O(E log E); the disjoint-set work is effectively linear with
 * path compression and union by rank. An undirected graph is required because
 * a directed minimum spanning arborescence is a different problem.
 */
final class Kruskal
{
    public static function run(Graph $graph): MstResult
    {
        if ($graph->directed()) {
            throw new InvalidArgumentException('Kruskal requires an undirected graph');
        }

        $edges = $graph->edges();
        usort($edges, static function (Edge $left, Edge $right): int {
            $byWeight = $left->weight <=> $right->weight;

            if ($byWeight !== 0) {
                return $byWeight;
            }

            $byFrom = $left->from <=> $right->from;

            return $byFrom !== 0 ? $byFrom : $left->to <=> $right->to;
        });
        $sets = new UnionFind();

        for ($vertex = 0; $vertex < $graph->vertexCount(); ++$vertex) {
            $sets->makeSet($vertex);
        }

        $mst = [];
        $weight = 0.0;

        foreach ($edges as $edge) {
            if (!$sets->union($edge->from, $edge->to)) {
                continue;
            }

            $mst[] = $edge;
            $weight += $edge->weight;

            if (count($mst) === $graph->vertexCount() - 1) {
                break;
            }
        }

        return new MstResult($mst, $weight);
    }
}
