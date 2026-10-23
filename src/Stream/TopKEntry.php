<?php

declare(strict_types=1);

namespace AlgorithmsLab\Stream;

/**
 * One key/count pair held by the exact top-K heap.
 *
 * The key is immutable so the position map cannot be invalidated by a rename;
 * the count is updated in place and the heap repairs its order.
 */
final class TopKEntry
{
    public function __construct(
        public readonly string $key,
        public int $count,
    ) {
    }
}
