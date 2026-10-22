<?php

declare(strict_types=1);

namespace AlgorithmsLab\Foundation;

use UnderflowException;

/**
 * A binary min-heap with a position map and decrease-key.
 *
 * Dijkstra's decrease-key variant needs to find a vertex already in the heap
 * and lower its priority without pushing a duplicate. The map stores the
 * current heap index; every swap updates both sides of that relationship.
 * Ties are broken by vertex id so the queue is deterministic.
 */
final class IndexedMinHeap
{
    /** @var list<array{vertex: int, priority: float}> */
    private array $heap = [];

    /** @var array<int, int> vertex => heap index */
    private array $positions = [];

    /**
     * Insert a vertex or lower its existing priority.
     *
     * Raising a priority is ignored: Dijkstra only ever improves distances, and
     * silently accepting a worse priority would corrupt the heap invariant.
     */
    public function insertOrDecrease(int $vertex, float $priority): void
    {
        if (isset($this->positions[$vertex])) {
            $index = $this->positions[$vertex];

            if ($priority >= $this->heap[$index]['priority']) {
                return;
            }

            $this->heap[$index]['priority'] = $priority;
            $this->siftUp($index);

            return;
        }

        $this->positions[$vertex] = count($this->heap);
        $this->heap[] = ['vertex' => $vertex, 'priority' => $priority];
        $this->siftUp(count($this->heap) - 1);
    }

    /**
     * @return array{vertex: int, priority: float}
     */
    public function extractMin(): array
    {
        if ($this->heap === []) {
            throw new UnderflowException('Cannot extract from an empty heap');
        }

        $root = $this->heap[0];
        $last = array_pop($this->heap);
        unset($this->positions[$root['vertex']]);

        if ($this->heap !== []) {
            $this->heap[0] = $last;
            $this->positions[$last['vertex']] = 0;
            $this->siftDown(0);
        }

        return $root;
    }

    public function isEmpty(): bool
    {
        return $this->heap === [];
    }

    public function count(): int
    {
        return count($this->heap);
    }

    public function contains(int $vertex): bool
    {
        return isset($this->positions[$vertex]);
    }

    public function priorityOf(int $vertex): ?float
    {
        if (!isset($this->positions[$vertex])) {
            return null;
        }

        return $this->heap[$this->positions[$vertex]]['priority'];
    }

    private function siftUp(int $index): void
    {
        while ($index > 0) {
            $parent = intdiv($index - 1, 2);

            if (!$this->comesBefore($index, $parent)) {
                return;
            }

            $this->swap($index, $parent);
            $index = $parent;
        }
    }

    private function siftDown(int $index): void
    {
        $count = count($this->heap);

        while (true) {
            $left = 2 * $index + 1;

            if ($left >= $count) {
                return;
            }

            $best = $left;
            $right = $left + 1;

            if ($right < $count && $this->comesBefore($right, $left)) {
                $best = $right;
            }

            if (!$this->comesBefore($best, $index)) {
                return;
            }

            $this->swap($index, $best);
            $index = $best;
        }
    }

    private function comesBefore(int $left, int $right): bool
    {
        $leftItem = $this->heap[$left];
        $rightItem = $this->heap[$right];

        if ($leftItem['priority'] !== $rightItem['priority']) {
            return $leftItem['priority'] < $rightItem['priority'];
        }

        return $leftItem['vertex'] < $rightItem['vertex'];
    }

    private function swap(int $left, int $right): void
    {
        [$this->heap[$left], $this->heap[$right]] = [$this->heap[$right], $this->heap[$left]];
        $this->positions[$this->heap[$left]['vertex']] = $left;
        $this->positions[$this->heap[$right]['vertex']] = $right;
    }
}
