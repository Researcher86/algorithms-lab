<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use AlgorithmsLab\Foundation\DeterministicRandom;
use InvalidArgumentException;

/**
 * Deterministic graph generators.
 *
 * A generator returns canonical edges only; each representation expands them
 * into arcs if it is undirected. Keeping generation separate from storage is
 * what lets one edge list feed the representation and algorithm experiments.
 */
final class GraphGenerator
{
    /**
     * @return list<Edge>
     */
    public static function path(int $vertices, float $weight = 1.0): array
    {
        self::assertVertices($vertices);
        $edges = [];

        for ($vertex = 0; $vertex + 1 < $vertices; ++$vertex) {
            $edges[] = new Edge($vertex, $vertex + 1, $weight);
        }

        return $edges;
    }

    /**
     * @return list<Edge>
     */
    public static function star(int $vertices, float $weight = 1.0): array
    {
        self::assertVertices($vertices);
        $edges = [];

        for ($vertex = 1; $vertex < $vertices; ++$vertex) {
            $edges[] = new Edge(0, $vertex, $weight);
        }

        return $edges;
    }

    /**
     * @return list<Edge>
     */
    public static function grid(int $rows, int $columns, float $weight = 1.0): array
    {
        if ($rows < 0 || $columns < 0) {
            throw new InvalidArgumentException('Grid dimensions cannot be negative');
        }

        $edges = [];

        for ($row = 0; $row < $rows; ++$row) {
            for ($column = 0; $column < $columns; ++$column) {
                $vertex = $row * $columns + $column;

                if ($column + 1 < $columns) {
                    $edges[] = new Edge($vertex, $vertex + 1, $weight);
                }

                if ($row + 1 < $rows) {
                    $edges[] = new Edge($vertex, $vertex + $columns, $weight);
                }
            }
        }

        return $edges;
    }

    /**
     * @return list<Edge>
     */
    public static function complete(int $vertices, float $weight = 1.0, bool $directed = false): array
    {
        self::assertVertices($vertices);
        $edges = [];

        for ($from = 0; $from < $vertices; ++$from) {
            for ($to = $directed ? 0 : $from + 1; $to < $vertices; ++$to) {
                if ($from !== $to) {
                    $edges[] = new Edge($from, $to, $weight);
                }
            }
        }

        return $edges;
    }

    /**
     * @return list<Edge>
     */
    public static function random(
        int $vertices,
        int $edges,
        DeterministicRandom $random,
        bool $directed = false,
        float $maxWeight = 1.0,
    ): array {
        self::assertVertices($vertices);

        if ($edges < 0) {
            throw new InvalidArgumentException('Edge count cannot be negative');
        }

        $maximum = $directed
            ? $vertices * ($vertices - 1)
            : intdiv($vertices * ($vertices - 1), 2);

        if ($edges > $maximum) {
            throw new InvalidArgumentException(sprintf(
                'Cannot create %d simple %s edges among %d vertices (maximum %d)',
                $edges,
                $directed ? 'directed' : 'undirected',
                $vertices,
                $maximum,
            ));
        }

        if ($edges > intdiv($maximum, 2)) {
            $all = self::complete($vertices, 1.0, $directed);
            $all = self::shuffle($all, $random);
            $chosen = array_slice($all, 0, $edges);

            return self::withWeights($chosen, $random, $maxWeight);
        }

        $chosen = [];
        $seen = [];
        $attempts = 0;
        $attemptLimit = max(1_000, $edges * 20);

        while (count($chosen) < $edges) {
            if (++$attempts > $attemptLimit) {
                throw new InvalidArgumentException('Could not generate enough distinct edges');
            }

            $from = $random->intBetween(0, $vertices - 1);
            $to = $random->intBetween(0, $vertices - 1);

            if ($from === $to) {
                continue;
            }

            if (!$directed && $from > $to) {
                [$from, $to] = [$to, $from];
            }

            $key = $from . ':' . $to;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $chosen[] = new Edge($from, $to, 1.0);
        }

        return self::withWeights($chosen, $random, $maxWeight);
    }

    /**
     * A DAG whose edges always point from a lower to a higher vertex.
     *
     * @return list<Edge>
     */
    public static function randomDag(
        int $vertices,
        int $edges,
        DeterministicRandom $random,
        float $maxWeight = 1.0,
    ): array {
        self::assertVertices($vertices);

        $maximum = intdiv($vertices * ($vertices - 1), 2);

        if ($edges < 0 || $edges > $maximum) {
            throw new InvalidArgumentException(sprintf(
                'Cannot create %d DAG edges among %d vertices (maximum %d)',
                $edges,
                $vertices,
                $maximum,
            ));
        }

        if ($edges > intdiv($maximum, 2)) {
            $all = [];

            for ($from = 0; $from < $vertices; ++$from) {
                for ($to = $from + 1; $to < $vertices; ++$to) {
                    $all[] = new Edge($from, $to, 1.0);
                }
            }

            $all = self::shuffle($all, $random);

            return self::withWeights(array_slice($all, 0, $edges), $random, $maxWeight);
        }

        $chosen = [];
        $seen = [];
        $attempts = 0;
        $attemptLimit = max(1_000, $edges * 20);

        while (count($chosen) < $edges) {
            if (++$attempts > $attemptLimit) {
                throw new InvalidArgumentException('Could not generate enough distinct DAG edges');
            }

            $from = $random->intBetween(0, $vertices - 1);
            $to = $random->intBetween(0, $vertices - 1);

            if ($from >= $to) {
                continue;
            }

            $key = $from . ':' . $to;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $chosen[] = new Edge($from, $to, 1.0);
        }

        return self::withWeights($chosen, $random, $maxWeight);
    }

    private static function assertVertices(int $vertices): void
    {
        if ($vertices < 0) {
            throw new InvalidArgumentException('Vertex count cannot be negative');
        }
    }

    /**
     * @param list<Edge> $edges
     * @return list<Edge>
     */
    private static function shuffle(array $edges, DeterministicRandom $random): array
    {
        for ($index = count($edges) - 1; $index > 0; --$index) {
            $swap = $random->intBetween(0, $index);
            [$edges[$index], $edges[$swap]] = [$edges[$swap], $edges[$index]];
        }

        return array_values($edges);
    }

    /**
     * @param list<Edge> $edges
     * @return list<Edge>
     */
    private static function withWeights(array $edges, DeterministicRandom $random, float $maxWeight): array
    {
        if ($maxWeight <= 0.0) {
            throw new InvalidArgumentException('Maximum edge weight must be positive');
        }

        if ($maxWeight <= 1.0) {
            return $edges;
        }

        $weighted = [];

        foreach ($edges as $edge) {
            $weighted[] = new Edge($edge->from, $edge->to, (float) $random->intBetween(1, (int) $maxWeight));
        }

        return $weighted;
    }
}
