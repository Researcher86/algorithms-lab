<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

/**
 * The read-only graph contract used by every algorithm in this chapter.
 *
 * Vertex ids are integers in [0, vertexCount). An undirected graph reports each
 * edge once in `edges()` (with from <= to) and both directions in
 * `neighbors()`. Self-loops are allowed. The graph is simple: adding an edge
 * that already exists replaces its weight rather than creating a parallel edge.
 */
interface Graph
{
    public function vertexCount(): int;

    /**
     * Number of simple edges, not directed arcs. An undirected edge counts once.
     */
    public function edgeCount(): int;

    public function directed(): bool;

    /**
     * @return list<Edge>
     */
    public function edges(): array;

    /**
     * @return array<int, float> neighbour id => weight
     */
    public function neighbors(int $vertex): array;

    public function hasEdge(int $from, int $to): bool;

    public function weight(int $from, int $to): ?float;
}
