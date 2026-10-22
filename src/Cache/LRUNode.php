<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

/**
 * A node in the LRU list. The key is kept so eviction can remove it from the
 * map without a reverse lookup.
 */
final class LRUNode
{
    public ?LRUNode $prev = null;

    public ?LRUNode $next = null;

    public function __construct(
        public string $key,
        public mixed $value,
    ) {
    }
}
