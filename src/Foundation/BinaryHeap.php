<?php

declare(strict_types=1);

namespace AlgorithmsLab\Foundation;

use UnderflowException;

/**
 * A binary heap over arbitrary values, ordered by a Comparator.
 *
 * The heap is deliberately not an SplHeap wrapper: every comparison has to go
 * through the Comparator so that the operation counters include the heap's own
 * work rather than hiding it inside an extension.
 */
final class BinaryHeap
{
    /**
     * Sequential by construction; typed as a plain int-keyed array because
     * PHPStan cannot prove list-ness through every in-place swap.
     *
     * @var array<int, mixed>
     */
    private array $values = [];

    public function __construct(
        private readonly Comparator $comparator,
        private readonly bool $minHeap = true,
    ) {
    }

    public function insert(mixed $value): void
    {
        $this->values[] = $value;
        $this->siftUp(count($this->values) - 1);
    }

    public function peek(): mixed
    {
        if ($this->values === []) {
            throw new UnderflowException('Cannot peek an empty heap');
        }

        return $this->values[0];
    }

    public function extract(): mixed
    {
        if ($this->values === []) {
            throw new UnderflowException('Cannot extract from an empty heap');
        }

        $root = $this->values[0];
        $last = array_pop($this->values);

        if ($this->values !== []) {
            $this->values[0] = $last;
            $this->siftDown(0);
        }

        return $root;
    }

    public function count(): int
    {
        return count($this->values);
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    public function clear(): void
    {
        $this->values = [];
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
        $count = count($this->values);

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
        $order = $this->comparator->compare($this->values[$left], $this->values[$right]);

        return $this->minHeap ? $order < 0 : $order > 0;
    }

    private function swap(int $left, int $right): void
    {
        [$this->values[$left], $this->values[$right]] = [$this->values[$right], $this->values[$left]];
    }
}
