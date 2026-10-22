<?php

declare(strict_types=1);

namespace AlgorithmsLab\HashTable;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * Separate chaining: every bucket is a list of Entry objects.
 *
 * This is the readable baseline. It pays for pointers and one object per pair,
 * but collisions never fill the table and deletion is trivial. The open
 * addressing implementation exists to show what that convenience costs.
 */
final class ChainingHashTable implements HashTable
{
    private const DEFAULT_MAX_LOAD_FACTOR = 0.75;

    /** @var array<int, list<Entry>> */
    private array $buckets;

    private int $size = 0;

    private readonly HashFunction $hashFunction;

    private readonly Counters $counters;

    private readonly float $maxLoadFactor;

    private readonly bool $autoResize;

    public function __construct(
        private int $capacity = 16,
        ?HashFunction $hashFunction = null,
        ?Counters $counters = null,
        float $maxLoadFactor = self::DEFAULT_MAX_LOAD_FACTOR,
        bool $autoResize = true,
    ) {
        if ($capacity < 1) {
            throw new InvalidArgumentException('Capacity must be at least 1');
        }

        if ($maxLoadFactor <= 0.0 || $maxLoadFactor > 1.0) {
            throw new InvalidArgumentException('Max load factor must be in (0, 1]');
        }

        $this->hashFunction = $hashFunction ?? HashFunction::fnv1a64();
        $this->counters = $counters ?? new Counters();
        $this->maxLoadFactor = $maxLoadFactor;
        $this->autoResize = $autoResize;
        $this->buckets = array_fill(0, $capacity, []);
    }

    public function put(string $key, mixed $value): void
    {
        $index = $this->indexFor($key);

        foreach ($this->buckets[$index] as $position => $entry) {
            ++$this->counters->comparisons;

            if ($entry->key === $key) {
                $this->buckets[$index][$position] = new Entry($key, $value);

                return;
            }
        }

        if ($this->buckets[$index] !== []) {
            ++$this->counters->collisions;
        }

        $this->buckets[$index][] = new Entry($key, $value);
        ++$this->size;

        if ($this->autoResize && $this->loadFactor() > $this->maxLoadFactor) {
            $this->resize($this->capacity * 2);
        }
    }

    public function get(string $key): mixed
    {
        return $this->find($key)?->value;
    }

    public function has(string $key): bool
    {
        return $this->find($key) !== null;
    }

    public function remove(string $key): bool
    {
        $index = $this->indexFor($key);

        foreach ($this->buckets[$index] as $position => $entry) {
            ++$this->counters->probes;
            ++$this->counters->comparisons;

            if ($entry->key !== $key) {
                continue;
            }

            array_splice($this->buckets[$index], $position, 1);
            --$this->size;

            return true;
        }

        return false;
    }

    public function count(): int
    {
        return $this->size;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function loadFactor(): float
    {
        return $this->size / $this->capacity;
    }

    public function counters(): Counters
    {
        return $this->counters;
    }

    /**
     * @return list<int> bucket lengths in index order
     */
    public function bucketLengths(): array
    {
        return array_values(array_map(
            static fn (array $bucket): int => count($bucket),
            $this->buckets,
        ));
    }

    public function maxBucketLength(): int
    {
        $maximum = 0;

        foreach ($this->bucketLengths() as $length) {
            $maximum = max($maximum, $length);
        }

        return $maximum;
    }

    /**
     * Rebuild the table at a new capacity. Exposed because the growth
     * experiment has to separate ordinary inserts from the rehash itself.
     */
    public function resize(int $capacity): void
    {
        if ($capacity < $this->size || $capacity < 1) {
            throw new InvalidArgumentException('New capacity cannot be smaller than the number of entries');
        }

        $oldBuckets = $this->buckets;
        $this->capacity = $capacity;
        $this->buckets = array_fill(0, $capacity, []);
        $this->size = 0;
        ++$this->counters->rehashes;

        foreach ($oldBuckets as $bucket) {
            foreach ($bucket as $entry) {
                $index = $this->indexFor($entry->key);

                if ($this->buckets[$index] !== []) {
                    ++$this->counters->collisions;
                }

                $this->buckets[$index][] = $entry;
                ++$this->size;
            }
        }
    }

    public function clear(): void
    {
        $this->buckets = array_fill(0, $this->capacity, []);
        $this->size = 0;
        $this->counters->reset();
    }

    private function find(string $key): ?Entry
    {
        $index = $this->indexFor($key);

        foreach ($this->buckets[$index] as $entry) {
            ++$this->counters->probes;
            ++$this->counters->comparisons;

            if ($entry->key === $key) {
                return $entry;
            }
        }

        return null;
    }

    private function indexFor(string $key): int
    {
        ++$this->counters->hashCalls;

        return $this->hashFunction->hash($key) % $this->capacity;
    }
}
