<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

use InvalidArgumentException;
use OutOfRangeException;

/**
 * Edge list: the smallest representation and the slowest neighbour query.
 *
 * It is append-only and intentionally allows parallel edges. The generators
 * produce simple graphs, so the algorithms see the same logical graph as the
 * other representations; the baseline shows what scanning every edge costs.
 */
final class EdgeList implements MutableGraph
{
    /** @var list<Edge> */
    private array $edges = [];

    public function __construct(
        private readonly int $vertexCount,
        private readonly bool $directed = false,
    ) {
        if ($vertexCount < 0) {
            throw new InvalidArgumentException('Vertex count cannot be negative');
        }
    }

    public function addEdge(int $from, int $to, float $weight = 1.0): void
    {
        $this->assertVertex($from);
        $this->assertVertex($to);
        $this->assertWeight($weight);

        if (!$this->directed && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        foreach ($this->edges as $index => $edge) {
            if ($edge->from === $from && $edge->to === $to) {
                $this->edges[$index] = new Edge($from, $to, $weight);

                return;
            }
        }

        $this->edges[] = new Edge($from, $to, $weight);
    }

    public function vertexCount(): int
    {
        return $this->vertexCount;
    }

    public function edgeCount(): int
    {
        return count($this->edges);
    }

    public function directed(): bool
    {
        return $this->directed;
    }

    public function edges(): array
    {
        return $this->edges;
    }

    public function neighbors(int $vertex): array
    {
        $this->assertVertex($vertex);
        $neighbors = [];

        foreach ($this->edges as $edge) {
            if ($edge->from === $vertex) {
                $neighbors[$edge->to] = $edge->weight;
            }

            if (!$this->directed && $edge->to === $vertex && $edge->from !== $vertex) {
                $neighbors[$edge->from] = $edge->weight;
            }
        }

        return $neighbors;
    }

    public function hasEdge(int $from, int $to): bool
    {
        $this->assertVertex($from);
        $this->assertVertex($to);

        return $this->weight($from, $to) !== null;
    }

    public function weight(int $from, int $to): ?float
    {
        $this->assertVertex($from);
        $this->assertVertex($to);

        foreach ($this->edges as $edge) {
            if ($edge->from === $from && $edge->to === $to) {
                return $edge->weight;
            }

            if (!$this->directed && $edge->from === $to && $edge->to === $from) {
                return $edge->weight;
            }
        }

        return null;
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
