<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

/**
 * The current value and priority of one heap-backed LFU entry.
 */
final class HeapLFUEntry
{
    public function __construct(
        public string $key,
        public mixed $value,
        public int $frequency,
        public int $lastUsed,
    ) {
    }
}
