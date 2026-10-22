<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use InvalidArgumentException;
use OutOfRangeException;

/**
 * Adjacency matrix: a flat V×V array of weights.
 *
 * Absence is represented by NAN rather than 0.0 so a legitimate zero-weight
 * edge is still distinguishable. The matrix is dense by design; this is the
 * representation the memory experiment uses to show what V² costs when V is
 * large.
 */
final class AdjacencyMatrix implements MutableGraph
{
    /** @var array<int, float> */
    private array $matrix;

    private int $edgeCount = 0;

    public function __construct(
        private readonly int $vertexCount,
        private readonly bool $directed = false,
    ) {
        if ($vertexCount < 0) {
            throw new InvalidArgumentException('Vertex count cannot be negative');
        }

        $this->matrix = array_fill(0, $vertexCount * $vertexCount, NAN);
    }

    public function addEdge(int $from, int $to, float $weight = 1.0): void
    {
        $this->assertVertex($from);
        $this->assertVertex($to);
        $this->assertWeight($weight);

        if (!$this->hasEdge($from, $to)) {
            ++$this->edgeCount;
        }

        $this->matrix[$this->index($from, $to)] = $weight;

        if (!$this->directed && $from !== $to) {
            $this->matrix[$this->index($to, $from)] = $weight;
        }
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
            for ($to = 0; $to < $this->vertexCount; ++$to) {
                if ($this->directed || $from <= $to) {
                    $weight = $this->matrix[$this->index($from, $to)];

                    if (!is_nan($weight)) {
                        $edges[] = new Edge($from, $to, $weight);
                    }
                }
            }
        }

        return $edges;
    }

    public function neighbors(int $vertex): array
    {
        $this->assertVertex($vertex);
        $neighbors = [];

        for ($to = 0; $to < $this->vertexCount; ++$to) {
            $weight = $this->matrix[$this->index($vertex, $to)];

            if (!is_nan($weight)) {
                $neighbors[$to] = $weight;
            }
        }

        return $neighbors;
    }

    public function hasEdge(int $from, int $to): bool
    {
        $this->assertVertex($from);
        $this->assertVertex($to);

        return !is_nan($this->matrix[$this->index($from, $to)]);
    }

    public function weight(int $from, int $to): ?float
    {
        $this->assertVertex($from);
        $this->assertVertex($to);
        $weight = $this->matrix[$this->index($from, $to)];

        return is_nan($weight) ? null : $weight;
    }

    private function index(int $from, int $to): int
    {
        return $from * $this->vertexCount + $to;
    }

    private function assertVertex(int $vertex): void
    {
        if ($vertex < 0 || $vertex >= $this->vertexCount) {
            throw new OutOfRangeException(sprintf('Unknown graph vertex: %d', $vertex));
        }
    }

    private function assertWeight(float $weight): void
    {
        if (!is_finite($weight)) {
            throw new InvalidArgumentException('Edge weight must be finite');
        }
    }
}
