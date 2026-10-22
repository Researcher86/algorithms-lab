<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * LRU implemented with PHP's own insertion-ordered array.
 *
 * Deleting and reinserting a key moves it to the end, so `array_key_first()`
 * is the eviction candidate. This is the baseline that says "the language
 * already gave you the data structure"; the linked-list LRU exists to make the
 * pointer work explicit.
 */
final class ArrayLRU implements Cache
{
    /** @var array<string, mixed> */
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
        if (!array_key_exists($key, $this->entries)) {
            $this->stats->recordMiss();

            return null;
        }

        $this->stats->recordHit();
        $value = $this->entries[$key];
        unset($this->entries[$key]);
        $this->entries[$key] = $value;

        return $value;
    }

    public function put(string $key, mixed $value): void
    {
        if ($this->capacity === 0) {
            return;
        }

        if (array_key_exists($key, $this->entries)) {
            unset($this->entries[$key]);
            $this->entries[$key] = $value;

            return;
        }

        if (count($this->entries) >= $this->capacity) {
            $oldest = array_key_first($this->entries);

            if ($oldest !== null) {
                unset($this->entries[$oldest]);
                $this->stats->recordEviction();
            }
        }

        $this->entries[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->entries);
    }

    public function remove(string $key): bool
    {
        if (!array_key_exists($key, $this->entries)) {
            return false;
        }

        unset($this->entries[$key]);

        return true;
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
        return array_keys($this->entries);
    }

    public function clear(): void
    {
        $this->entries = [];
        $this->stats->reset();
        $this->counters->reset();
    }
}
