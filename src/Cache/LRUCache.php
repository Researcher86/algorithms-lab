<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;
use LogicException;

/**
 * LRU with a hash map and a doubly linked list.
 *
 * The map answers "is it here?" and the list answers "who goes next?" in O(1).
 * Every successful read moves one node to the front; the node before the tail
 * is the eviction candidate. The sentinels remove the empty-list branches from
 * the hot path.
 */
final class LRUCache implements Cache
{
    /** @var array<string, LRUNode> */
    private array $map = [];

    private LRUNode $head;

    private LRUNode $tail;

    private readonly CacheStats $stats;

    private readonly Counters $counters;

    public function __construct(
        private readonly int $capacity,
        ?CacheStats $stats = null,
        ?Counters $counters = null,
    ) {
        if ($capacity < 0) {
            throw new InvalidArgumentException('Cache capacity cannot be negative');
        }

        $this->head = new LRUNode('', null);
        $this->tail = new LRUNode('', null);
        $this->head->next = $this->tail;
        $this->tail->prev = $this->head;
        $this->stats = $stats ?? new CacheStats();
        $this->counters = $counters ?? new Counters();
    }

    public function get(string $key): mixed
    {
        if (!isset($this->map[$key])) {
            $this->stats->recordMiss();

            return null;
        }

        $this->stats->recordHit();
        $node = $this->map[$key];
        $this->moveToFront($node);

        return $node->value;
    }

    public function put(string $key, mixed $value): void
    {
        if ($this->capacity === 0) {
            return;
        }

        if (isset($this->map[$key])) {
            $node = $this->map[$key];
            $node->value = $value;
            $this->moveToFront($node);

            return;
        }

        $node = new LRUNode($key, $value);
        $this->map[$key] = $node;
        $this->addAfterHead($node);

        if (count($this->map) > $this->capacity) {
            $this->evictOldest();
        }
    }

    public function has(string $key): bool
    {
        return isset($this->map[$key]);
    }

    public function remove(string $key): bool
    {
        if (!isset($this->map[$key])) {
            return false;
        }

        $node = $this->map[$key];
        $this->unlink($node);
        unset($this->map[$key]);

        return true;
    }

    public function count(): int
    {
        return count($this->map);
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function stats(): CacheStats
    {
        return $this->stats;
    }

    public function counters(): Counters
    {
        return $this->counters;
    }

    /**
     * @return list<string> keys from most to least recently used
     */
    public function keys(): array
    {
        $keys = [];
        $node = $this->head->next;

        while ($node !== null && $node !== $this->tail) {
            $keys[] = $node->key;
            $node = $node->next;
        }

        return $keys;
    }

    public function clear(): void
    {
        $this->map = [];
        $this->head->next = $this->tail;
        $this->tail->prev = $this->head;
        $this->stats->reset();
        $this->counters->reset();
    }

    private function moveToFront(LRUNode $node): void
    {
        if ($this->head->next === $node) {
            return;
        }

        $this->unlink($node);
        $this->addAfterHead($node);
    }

    private function addAfterHead(LRUNode $node): void
    {
        $next = $this->head->next;

        if ($next === null) {
            throw new LogicException('LRU head has no next node');
        }

        $node->prev = $this->head;
        $node->next = $next;
        $next->prev = $node;
        $this->head->next = $node;
        ++$this->counters->relinks;
    }

    private function unlink(LRUNode $node): void
    {
        $prev = $node->prev;
        $next = $node->next;

        if ($prev !== null) {
            $prev->next = $next;
        }

        if ($next !== null) {
            $next->prev = $prev;
        }

        $node->prev = null;
        $node->next = null;
        ++$this->counters->relinks;
    }

    private function evictOldest(): void
    {
        $oldest = $this->tail->prev;

        if ($oldest === null || $oldest === $this->head) {
            return;
        }

        $this->unlink($oldest);
        unset($this->map[$oldest->key]);
        $this->stats->recordEviction();
    }
}
