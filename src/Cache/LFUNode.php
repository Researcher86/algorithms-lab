<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

/**
 * A node in one LFU frequency bucket.
 */
final class LFUNode
{
    public ?LFUNode $prev = null;

    public ?LFUNode $next = null;

    public function __construct(
        public string $key,
        public mixed $value,
        public int $frequency,
    ) {
    }
}
