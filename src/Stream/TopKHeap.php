<?php

declare(strict_types=1);

namespace AlgorithmsLab\Stream;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * Exact top-K over a stream, with a full frequency map behind it.
 *
 * The heap is bounded by K, but `$counts` is not: an exact answer needs a
 * count for every distinct key seen. That distinction is the chapter's point.
 * The heap is indexed so an existing key can be repaired in O(log K) instead
 * of rebuilding the heap; a new key that beats the minimum replaces it.
 */
final class TopKHeap
{
    /** @var array<int, TopKEntry> */
    private array $heap = [];

    /** @var array<string, int> */
    private array $positions = [];

    /** @var array<string, int> */
    private array $counts = [];

    public function __construct(
        private readonly int $k,
        private readonly ?Counters $counters = null,
    ) {
        if ($k < 1) {
            throw new InvalidArgumentException('K must be at least 1');
        }
    }

    public function observe(string $key, int $increment = 1): void
    {
        if ($increment < 1) {
            throw new InvalidArgumentException('Increment must be positive');
        }

        $count = ($this->counts[$key] ?? 0) + $increment;
        $this->counts[$key] = $count;

        if (isset($this->positions[$key])) {
            $index = $this->positions[$key];
            $this->heap[$index]->count = $count;
            $this->siftDown($index);
            $this->siftUp($index);

            return;
        }

        $entry = new TopKEntry($key, $count);

        if (count($this->heap) < $this->k) {
            $this->heap[] = $entry;
            $this->positions[$key] = count($this->heap) - 1;
            $this->siftUp(count($this->heap) - 1);

            return;
        }

        $worst = $this->heap[0];

        if (!$this->isBetter($entry, $worst)) {
            return;
        }

        unset($this->positions[$worst->key]);
        $this->heap[0] = $entry;
        $this->positions[$key] = 0;
        $this->siftDown(0);
    }

    /**
     * @return list<array{key: string, count: int}>
     */
    public function top(): array
    {
        $entries = $this->heap;
        usort($entries, fn (TopKEntry $left, TopKEntry $right): int => $this->compareBest($left, $right));
        $result = [];

        foreach ($entries as $entry) {
            $result[] = ['key' => $entry->key, 'count' => $entry->count];
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return $this->counts;
    }

    public function countFor(string $key): int
    {
        return $this->counts[$key] ?? 0;
    }

    public function distinctCount(): int
    {
        return count($this->counts);
    }

    public function size(): int
    {
        return count($this->heap);
    }

    public function k(): int
    {
        return $this->k;
    }

    private function isBetter(TopKEntry $left, TopKEntry $right): bool
    {
        $this->counters?->add('comparisons');

        if ($left->count !== $right->count) {
            return $left->count > $right->count;
        }

        return strcmp($left->key, $right->key) < 0;
    }

    private function compareBest(TopKEntry $left, TopKEntry $right): int
    {
        $this->counters?->add('comparisons');

        return ($right->count <=> $left->count) ?: strcmp($left->key, $right->key);
    }

    private function isWorse(TopKEntry $left, TopKEntry $right): bool
    {
        $this->counters?->add('comparisons');

        if ($left->count !== $right->count) {
            return $left->count < $right->count;
        }

        return strcmp($left->key, $right->key) > 0;
    }

    private function siftUp(int $index): void
    {
        while ($index > 0) {
            $parent = intdiv($index - 1, 2);

            if (!$this->isWorse($this->heap[$index], $this->heap[$parent])) {
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

            $worst = $left;
            $right = $left + 1;

            if ($right < $count && $this->isWorse($this->heap[$right], $this->heap[$left])) {
                $worst = $right;
            }

            if (!$this->isWorse($this->heap[$worst], $this->heap[$index])) {
                return;
            }

            $this->swap($index, $worst);
            $index = $worst;
        }
    }

    private function swap(int $left, int $right): void
    {
        [$this->heap[$left], $this->heap[$right]] = [$this->heap[$right], $this->heap[$left]];
        $this->positions[$this->heap[$left]->key] = $left;
        $this->positions[$this->heap[$right]->key] = $right;
    }
}
