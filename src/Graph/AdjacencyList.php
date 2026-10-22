<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use InvalidArgumentException;
use OutOfRangeException;

/**
 * Adjacency list: a hash map of neighbour maps per vertex.
 *
 * This is the representation most algorithms want: O(V + E) memory and
 * iteration proportional to the degree. The inner map also gives O(1) edge
 * lookup by target, which is more than a pure list needs.
 */
final class AdjacencyList implements MutableGraph
{
    /** @var list<array<int, float>> */
    private array $adjacency;

    private int $edgeCount = 0;

    public function __construct(
        private readonly int $vertexCount,
        private readonly bool $directed = false,
    ) {
        if ($vertexCount < 0) {
            throw new InvalidArgumentException('Vertex count cannot be negative');
        }

        $this->adjacency = array_fill(0, $vertexCount, []);
    }

    public function addEdge(int $from, int $to, float $weight = 1.0): void
    {
        $this->assertVertex($from);
        $this->assertVertex($to);
        $this->assertWeight($weight);

        if ($this->directed) {
            if (!array_key_exists($to, $this->adjacency[$from])) {
                ++$this->edgeCount;
            }

            $this->adjacency[$from][$to] = $weight;

            return;
        }

        if (!array_key_exists($to, $this->adjacency[$from])) {
            ++$this->edgeCount;
        }

        $this->adjacency[$from][$to] = $weight;

        if ($from !== $to) {
            $this->adjacency[$to][$from] = $weight;
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

        foreach ($this->adjacency as $from => $neighbors) {
            foreach ($neighbors as $to => $weight) {
                if ($this->directed || $from <= $to) {
                    $edges[] = new Edge($from, $to, $weight);
                }
            }
        }

        return $edges;
    }

    public function neighbors(int $vertex): array
    {
        $this->assertVertex($vertex);

        return $this->adjacency[$vertex];
    }

    public function hasEdge(int $from, int $to): bool
    {
        $this->assertVertex($from);
        $this->assertVertex($to);

        return array_key_exists($to, $this->adjacency[$from]);
    }

    public function weight(int $from, int $to): ?float
    {
        $this->assertVertex($from);
        $this->assertVertex($to);

        return $this->adjacency[$from][$to] ?? null;
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
