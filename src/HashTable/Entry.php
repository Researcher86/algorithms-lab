<?php

declare(strict_types=1);

namespace AlgorithmsLab\HashTable;

/**
 * One key/value pair in a separate-chaining bucket.
 *
 * It is a small object rather than a two-element array so the memory
 * experiment can see the real cost of the representation: a PHP object header
 * plus two properties, not an array plus a hash table.
 */
final readonly class Entry
{
    public function __construct(
        public string $key,
        public mixed $value,
    ) {
    }
}
