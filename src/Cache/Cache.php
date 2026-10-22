<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

use AlgorithmsLab\Instrumentation\Counters;

/**
 * The operations every cache implementation in this chapter exposes.
 *
 * `get()` updates recency/frequency and the hit-or-miss counters. `has()`
 * deliberately does not: a probe must not change which entry is evicted.
 * `get()` returns null for a miss and for a stored null; `has()` is the way to
 * tell those cases apart.
 */
interface Cache
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function remove(string $key): bool;

    public function count(): int;

    public function capacity(): int;

    public function stats(): CacheStats;

    public function counters(): Counters;

    public function clear(): void;
}
