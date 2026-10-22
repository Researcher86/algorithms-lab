<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

use AlgorithmsLab\Foundation\BinaryHeap;
use AlgorithmsLab\Foundation\Comparator;
use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;
use LogicException;

/**
 * LFU with a min-heap and lazy deletion, as an O(log n) comparison point.
 *
 * Every access pushes a new `(frequency, lastUsed)` record. The old record is
 * left in the heap and discarded when it reaches the top: if its snapshot no
 * longer matches the live entry, it is stale. The price is memory that grows
 * with the number of accesses, not just the number of keys.
 */
final class HeapLFU implements Cache
{
    /** @var array<string, HeapLFUEntry> */
    private array $map = [];

    private readonly BinaryHeap $heap;

    private readonly CacheStats $stats;

    private readonly Counters $counters;

    private int $clock = 0;

    public function __construct(
        private readonly int $capacity,
        ?CacheStats $stats = null,
        ?Counters $counters = null,
    ) {
        if ($capacity < 0) {
            throw new InvalidArgumentException('Cache capacity cannot be negative');
        }

        $this->stats = $stats ?? new CacheStats();
        $this->counters = $counters ?? new Counters();
        $this->heap = new BinaryHeap(Comparator::of(static function (mixed $left, mixed $right): int {
            if (!$left instanceof HeapLFURecord || !$right instanceof HeapLFURecord) {
                throw new LogicException('HeapLFU heap contains a non-record');
            }

            if ($left->frequency !== $right->frequency) {
                return $left->frequency <=> $right->frequency;
            }

            return $left->lastUsed <=> $right->lastUsed;
        }));
    }

    public function get(string $key): mixed
    {
        if (!isset($this->map[$key])) {
            $this->stats->recordMiss();

            return null;
        }

        $this->stats->recordHit();
        $entry = $this->map[$key];
        ++$entry->frequency;
        $entry->lastUsed = ++$this->clock;
        $this->heap->insert(new HeapLFURecord($entry->key, $entry->frequency, $entry->lastUsed));

        return $entry->value;
    }

    public function put(string $key, mixed $value): void
    {
        if ($this->capacity === 0) {
            return;
        }

        if (isset($this->map[$key])) {
            $entry = $this->map[$key];
            $entry->value = $value;
            ++$entry->frequency;
            $entry->lastUsed = ++$this->clock;
            $this->heap->insert(new HeapLFURecord($entry->key, $entry->frequency, $entry->lastUsed));

            return;
        }

        if (count($this->map) >= $this->capacity) {
            $this->evict();

            if (count($this->map) >= $this->capacity) {
                throw new LogicException('HeapLFU could not make room for a new entry');
            }
        }

        $entry = new HeapLFUEntry($key, $value, 1, ++$this->clock);
        $this->map[$key] = $entry;
        $this->heap->insert(new HeapLFURecord($key, 1, $entry->lastUsed));
    }

    public function has(string $key): bool
    {
        return isset($this->map[$key]);
    }

    public function remove(string $key): bool
    {
        if (!isset($this->map[$key])) {
            return false;
        }

        unset($this->map[$key]);

        return true;
    }

    public function count(): int
    {
        return count($this->map);
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function stats(): CacheStats
    {
        return $this->stats;
    }

    public function counters(): Counters
    {
        return $this->counters;
    }

    public function frequencyOf(string $key): ?int
    {
        return isset($this->map[$key]) ? $this->map[$key]->frequency : null;
    }

    public function clear(): void
    {
        $this->map = [];
        $this->heap->clear();
        $this->clock = 0;
        $this->stats->reset();
        $this->counters->reset();
    }

    private function evict(): void
    {
        while (!$this->heap->isEmpty()) {
            $record = $this->heap->extract();
            ++$this->counters->nodeReads;

            if (!$record instanceof HeapLFURecord) {
                throw new LogicException('HeapLFU heap contains a non-record');
            }

            $entry = $this->map[$record->key] ?? null;

            if ($entry === null) {
                continue;
            }

            if ($entry->frequency !== $record->frequency || $entry->lastUsed !== $record->lastUsed) {
                continue;
            }

            unset($this->map[$record->key]);
            $this->stats->recordEviction();

            return;
        }
    }
}
