<?php

declare(strict_types=1);

namespace AlgorithmsLab\Stream;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * Misra-Gries frequency estimation for a bounded counter set.
 *
 * When the set is full and a new key arrives, every counter is decremented by
 * one and zero counters are removed; the arriving key is not inserted in that
 * step. A key that survives has a counter no larger than its true frequency
 * but is guaranteed to have appeared often enough to survive the
 * cancellation. It is the lossy-counting counterpart to Space-Saving's
 * replacement rule.
 */
final class MisraGries
{
    /** @var array<string, int> */
    private array $frequencies = [];

    public function __construct(
        private readonly int $capacity,
        private readonly ?Counters $counters = null,
    ) {
        if ($capacity < 1) {
            throw new InvalidArgumentException('Capacity must be at least 1');
        }
    }

    public function observe(string $key, int $count = 1): void
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Increment must be positive');
        }

        if (isset($this->frequencies[$key])) {
            $this->frequencies[$key] += $count;

            return;
        }

        if (count($this->frequencies) < $this->capacity) {
            $this->frequencies[$key] = $count;

            return;
        }

        foreach ($this->frequencies as $candidate => $value) {
            $this->counters?->add('comparisons');
            $remaining = $value - $count;

            if ($remaining <= 0) {
                unset($this->frequencies[$candidate]);
            } else {
                $this->frequencies[$candidate] = $remaining;
            }
        }

    }

    /**
     * @return list<array{key: string, count: int}>
     */
    public function top(int $k): array
    {
        if ($k < 1) {
            return [];
        }

        $entries = [];

        foreach ($this->frequencies as $key => $count) {
            $entries[] = ['key' => $key, 'count' => $count];
        }

        usort($entries, fn (array $left, array $right): int => ($right['count'] <=> $left['count']) ?: strcmp($left['key'], $right['key']));

        return array_slice($entries, 0, min($k, count($entries)));
    }

    public function estimate(string $key): int
    {
        return $this->frequencies[$key] ?? 0;
    }

    public function size(): int
    {
        return count($this->frequencies);
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    /**
     * @return array<string, int>
     */
    public function counters(): array
    {
        return $this->frequencies;
    }
}
