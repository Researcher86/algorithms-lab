<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use InvalidArgumentException;
use OutOfRangeException;

/**
 * Compressed sparse row: two flat arrays instead of one array per vertex.
 *
 * CSR gives up incremental insertion and neighbour maps in exchange for a
 * compact, cache-friendly layout. Building it is O(V + E); a neighbour query
 * returns a fresh map because the algorithm contract is representation-neutral.
 */
final class CsrGraph implements Graph
{
    /**
     * @param list<int> $offsets
     * @param list<int> $targets
     * @param list<float> $weights
     */
    private function __construct(
        private readonly int $vertexCount,
        private readonly bool $directed,
        private readonly array $offsets,
        private readonly array $targets,
        private readonly array $weights,
        private readonly int $edgeCount,
    ) {
    }

    /**
     * @param list<Edge> $edges
     */
    public static function fromEdges(int $vertexCount, array $edges, bool $directed = false): self
    {
        if ($vertexCount < 0) {
            throw new InvalidArgumentException('Vertex count cannot be negative');
        }

        /** @var array<int, int> $degree */
        $degree = array_fill(0, $vertexCount, 0);
        /** @var list<Edge> $simpleEdges */
        $simpleEdges = [];
        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($edges as $edge) {
            self::assertVertex($edge->from, $vertexCount);
            self::assertVertex($edge->to, $vertexCount);

            if (!is_finite($edge->weight)) {
                throw new InvalidArgumentException('Edge weight must be finite');
            }

            $from = $edge->from;
            $to = $edge->to;

            if (!$directed && $from > $to) {
                [$from, $to] = [$to, $from];
            }

            $key = $from . ':' . $to;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $simpleEdges[] = $edge;
            ++$degree[$from];

            if (!$directed && $from !== $to) {
                ++$degree[$to];
            }
        }

        /** @var list<int> $offsets */
        $offsets = [0];

        for ($vertex = 0; $vertex < $vertexCount; ++$vertex) {
            $offsets[] = $offsets[$vertex] + $degree[$vertex];
        }

        $arcs = $offsets[$vertexCount];
        /** @var array<int, int> $cursor */
        $cursor = $offsets;
        /** @var list<int> $targets */
        $targets = array_fill(0, $arcs, 0);
        /** @var list<float> $weights */
        $weights = array_fill(0, $arcs, 0.0);

        foreach ($simpleEdges as $edge) {
            $from = $edge->from;
            $to = $edge->to;

            if (!$directed && $from > $to) {
                [$from, $to] = [$to, $from];
            }

            $targets[$cursor[$from]] = $to;
            $weights[$cursor[$from]] = $edge->weight;
            ++$cursor[$from];

            if (!$directed && $from !== $to) {
                $targets[$cursor[$to]] = $from;
                $weights[$cursor[$to]] = $edge->weight;
                ++$cursor[$to];
            }
        }

        return new self($vertexCount, $directed, $offsets, array_values($targets), array_values($weights), count($simpleEdges));
    }

    public function vertexCount(): int
    {
        return $this->vertexCount;
    }

    public function edgeCount(): int
    {
        return $this->edgeCount;
    }

    public function directed(): bool
    {
        return $this->directed;
    }

    public function edges(): array
    {
        $edges = [];

        for ($from = 0; $from < $this->vertexCount; ++$from) {
            $start = $this->offsets[$from];
            $end = $this->offsets[$from + 1];

            for ($index = $start; $index < $end; ++$index) {
                $to = $this->targets[$index];

                if ($this->directed || $from <= $to) {
                    $edges[] = new Edge($from, $to, $this->weights[$index]);
                }
            }
        }

        return $edges;
    }

    public function neighbors(int $vertex): array
    {
        self::assertVertex($vertex, $this->vertexCount);
        $neighbors = [];
        $start = $this->offsets[$vertex];
        $end = $this->offsets[$vertex + 1];

        for ($index = $start; $index < $end; ++$index) {
            $neighbors[$this->targets[$index]] = $this->weights[$index];
        }

        return $neighbors;
    }

    public function hasEdge(int $from, int $to): bool
    {
        return $this->weight($from, $to) !== null;
    }

    public function weight(int $from, int $to): ?float
    {
        self::assertVertex($from, $this->vertexCount);
        self::assertVertex($to, $this->vertexCount);
        $start = $this->offsets[$from];
        $end = $this->offsets[$from + 1];

        for ($index = $start; $index < $end; ++$index) {
            if ($this->targets[$index] === $to) {
                return $this->weights[$index];
            }
        }

        return null;
    }

    private static function assertVertex(int $vertex, int $vertexCount): void
    {
        if ($vertex < 0 || $vertex >= $vertexCount) {
            throw new OutOfRangeException(sprintf('Unknown graph vertex: %d', $vertex));
        }
    }
}
