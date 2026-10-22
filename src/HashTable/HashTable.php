<?php

declare(strict_types=1);

namespace AlgorithmsLab\HashTable;

use AlgorithmsLab\Instrumentation\Counters;

/**
 * The operations every hash-table implementation in this chapter exposes.
 *
 * `get()` returns null for a missing key and for a stored null; callers that
 * need the distinction use `has()`. The contract is explicit because silently
 * treating "missing" and "stored null" as the same is a common bug.
 */
interface HashTable
{
    public function put(string $key, mixed $value): void;

    public function get(string $key): mixed;

    public function has(string $key): bool;

    public function remove(string $key): bool;

    public function count(): int;

    public function capacity(): int;

    public function loadFactor(): float;

    public function counters(): Counters;
}
