<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

/**
 * A heap record is an immutable snapshot of an entry's priority.
 *
 * A mutable heap entry would make old comparisons meaningless, so every
 * access pushes a new record and the old one becomes a lazy-deletion marker.
 */
final readonly class HeapLFURecord
{
    public function __construct(
        public string $key,
        public int $frequency,
        public int $lastUsed,
    ) {
    }
}
