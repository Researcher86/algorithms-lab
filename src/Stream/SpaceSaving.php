<?php

declare(strict_types=1);

namespace AlgorithmsLab\Stream;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * Space-Saving heavy hitters.
 *
 * At most `capacity` keys are kept. A new key replaces the current minimum
 * counter and inherits that minimum plus one, so the counters overestimate the
 * true frequencies but the error is bounded by the replacement threshold. The
 * minimum scan is O(capacity); the point of the structure is bounded memory,
 * not a better asymptotic constant than a heap.
 */
final class SpaceSaving
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

        $minimumKey = null;
        $minimum = PHP_INT_MAX;

        foreach ($this->frequencies as $candidate => $value) {
            $this->counters?->add('comparisons');
            if ($value < $minimum || ($value === $minimum && ($minimumKey === null || $candidate < $minimumKey))) {
                $minimum = $value;
                $minimumKey = $candidate;
            }
        }

        if ($minimumKey === null) {
            throw new \LogicException('A full Space-Saving set must have a minimum key');
        }

        unset($this->frequencies[$minimumKey]);
        $this->frequencies[$key] = $minimum + $count;
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
