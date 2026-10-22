<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * The honest O(n) LRU: a list of pairs and a linear scan for every operation.
 *
 * It exists so the chapter can show what the map-plus-list trick buys. The
 * list is ordered least to most recently used, so eviction is `array_shift()`
 * and a hit is `array_splice()` followed by an append.
 */
final class NaiveLRU implements Cache
{
    /** @var list<array{key: string, value: mixed}> */
    private array $entries = [];

    private readonly CacheStats $stats;

    private readonly Counters $counters;

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
    }

    public function get(string $key): mixed
    {
        foreach ($this->entries as $index => $entry) {
            ++$this->counters->comparisons;

            if ($entry['key'] !== $key) {
                continue;
            }

            array_splice($this->entries, $index, 1);
            $this->entries[] = $entry;
            $this->stats->recordHit();

            return $entry['value'];
        }

        $this->stats->recordMiss();

        return null;
    }

    public function put(string $key, mixed $value): void
    {
        if ($this->capacity === 0) {
            return;
        }

        foreach ($this->entries as $index => $entry) {
            ++$this->counters->comparisons;

            if ($entry['key'] !== $key) {
                continue;
            }

            $entry['value'] = $value;
            array_splice($this->entries, $index, 1);
            $this->entries[] = $entry;

            return;
        }

        if (count($this->entries) >= $this->capacity) {
            array_shift($this->entries);
            $this->stats->recordEviction();
        }

        $this->entries[] = ['key' => $key, 'value' => $value];
    }

    public function has(string $key): bool
    {
        foreach ($this->entries as $entry) {
            ++$this->counters->comparisons;

            if ($entry['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    public function remove(string $key): bool
    {
        foreach ($this->entries as $index => $entry) {
            ++$this->counters->comparisons;

            if ($entry['key'] !== $key) {
                continue;
            }

            array_splice($this->entries, $index, 1);

            return true;
        }

        return false;
    }

    public function count(): int
    {
        return count($this->entries);
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

    /**
     * @return list<string> keys from least to most recently used
     */
    public function keys(): array
    {
        return array_map(
            static fn (array $entry): string => $entry['key'],
            $this->entries,
        );
    }

    public function clear(): void
    {
        $this->entries = [];
        $this->stats->reset();
        $this->counters->reset();
    }
}
