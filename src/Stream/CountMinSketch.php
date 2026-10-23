<?php

declare(strict_types=1);

namespace AlgorithmsLab\Stream;

use AlgorithmsLab\Foundation\Hashers;
use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * Count-Min sketch: a fixed-size array of counters with one hash per row.
 *
 * An estimate is the minimum of the d row counters; collisions can only
 * overestimate, never underestimate, because every counter the key increments
 * is also incremented by the keys that collide with it. The sketch does not
 * enumerate its keys: finding candidates for top-K is a separate problem, and
 * the experiments state that explicitly.
 */
final class CountMinSketch
{
    /** @var array<int, array<int, int>> */
    private array $table;

    public function __construct(
        private readonly int $width,
        private readonly int $depth = 4,
        private readonly ?Counters $counters = null,
    ) {
        if ($width < 1) {
            throw new InvalidArgumentException('Width must be at least 1');
        }

        if ($depth < 1) {
            throw new InvalidArgumentException('Depth must be at least 1');
        }

        $this->table = array_fill(0, $depth, array_fill(0, $width, 0));
    }

    public function add(string $key, int $count = 1): void
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Increment must be positive');
        }

        for ($row = 0; $row < $this->depth; ++$row) {
            $this->counters?->add('hashCalls');
            $this->counters?->add('probes');
            $index = Hashers::xxh128($row . ':' . $key) % $this->width;
            $this->table[$row][$index] += $count;
        }
    }

    public function estimate(string $key): int
    {
        $minimum = null;

        for ($row = 0; $row < $this->depth; ++$row) {
            $this->counters?->add('hashCalls');
            $this->counters?->add('probes');
            $index = Hashers::xxh128($row . ':' . $key) % $this->width;
            $value = $this->table[$row][$index];

            if ($minimum === null || $value < $minimum) {
                $this->counters?->add('comparisons');
                $minimum = $value;
            }
        }

        return $minimum ?? 0;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function depth(): int
    {
        return $this->depth;
    }

    public function cells(): int
    {
        return $this->width * $this->depth;
    }
}
