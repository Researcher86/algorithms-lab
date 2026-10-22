<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

/**
 * One frequency's doubly linked list, ordered most to least recently used.
 *
 * Sentinels make every frequency bucket look non-empty to the pointer code;
 * `count` is what the cache consults before discarding a bucket.
 */
final class FrequencyBucket
{
    public LFUNode $head;

    public LFUNode $tail;

    public int $count = 0;

    public function __construct()
    {
        $this->head = new LFUNode('', null, 0);
        $this->tail = new LFUNode('', null, 0);
        $this->head->next = $this->tail;
        $this->tail->prev = $this->head;
    }

    public function isEmpty(): bool
    {
        return $this->count === 0;
    }
}
