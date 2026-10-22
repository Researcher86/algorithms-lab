<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * LFU with a map and one doubly linked list per frequency.
 *
 * The map finds a node in O(1); the bucket list gives O(1) promotion and
 * eviction. `minFrequency` is the only pointer that has to be maintained when
 * the least-frequent bucket empties. Within the minimum bucket the tail is the
 * least recently used node, so ties are broken LRU-style.
 *
 * This cache never decays: an item that was popular once keeps a high
 * frequency forever unless it is evicted. That is the failure mode the scan
 * experiment illustrates.
 */
final class LFUCache implements Cache
{
    /** @var array<string, LFUNode> */
    private array $map = [];

    /** @var array<int, FrequencyBucket> */
    private array $frequencies = [];

    private int $minFrequency = 0;

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
        $this->touch($node);

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
            $this->touch($node);

            return;
        }

        if (count($this->map) >= $this->capacity) {
            $this->evict();
        }

        $node = new LFUNode($key, $value, 1);
        $this->map[$key] = $node;
        $this->addToBucket($node);
        $this->minFrequency = 1;
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
        $frequency = $node->frequency;
        $bucket = $this->frequencies[$frequency] ?? null;

        if ($bucket !== null) {
            $this->unlink($node);
            --$bucket->count;

            if ($bucket->isEmpty()) {
                unset($this->frequencies[$frequency]);
            }
        }

        unset($this->map[$key]);

        if ($this->minFrequency === $frequency) {
            $this->recomputeMinFrequency();
        }

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

    public function minFrequency(): int
    {
        return $this->minFrequency;
    }

    public function frequencyOf(string $key): ?int
    {
        return isset($this->map[$key]) ? $this->map[$key]->frequency : null;
    }

    public function clear(): void
    {
        $this->map = [];
        $this->frequencies = [];
        $this->minFrequency = 0;
        $this->stats->reset();
        $this->counters->reset();
    }

    private function touch(LFUNode $node): void
    {
        $oldFrequency = $node->frequency;
        $oldBucket = $this->frequencies[$oldFrequency] ?? null;

        if ($oldBucket !== null) {
            $this->unlink($node);
            --$oldBucket->count;

            if ($oldBucket->isEmpty()) {
                unset($this->frequencies[$oldFrequency]);

                if ($this->minFrequency === $oldFrequency) {
                    ++$this->minFrequency;
                }
            }
        }

        ++$node->frequency;
        $this->addToBucket($node);
    }

    private function addToBucket(LFUNode $node): void
    {
        $frequency = $node->frequency;
        $bucket = $this->frequencies[$frequency] ??= new FrequencyBucket();
        $next = $bucket->head->next;

        if ($next === null) {
            throw new \LogicException('Frequency bucket head has no next node');
        }

        $node->prev = $bucket->head;
        $node->next = $next;
        $next->prev = $node;
        $bucket->head->next = $node;
        ++$bucket->count;
        ++$this->counters->relinks;
    }

    private function unlink(LFUNode $node): void
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

    private function evict(): void
    {
        if ($this->map === []) {
            return;
        }

        $frequency = $this->minFrequency;
        $bucket = $this->frequencies[$frequency] ?? null;

        if ($bucket === null || $bucket->isEmpty()) {
            $this->recomputeMinFrequency();
            $frequency = $this->minFrequency;
            $bucket = $this->frequencies[$frequency] ?? null;
        }

        if ($bucket === null) {
            return;
        }

        $victim = $bucket->tail->prev;

        if ($victim === null || $victim === $bucket->head) {
            return;
        }

        $this->unlink($victim);
        --$bucket->count;
        unset($this->map[$victim->key]);
        $this->stats->recordEviction();

        if ($bucket->isEmpty()) {
            unset($this->frequencies[$frequency]);
            $this->recomputeMinFrequency();
        }
    }

    private function recomputeMinFrequency(): void
    {
        if ($this->map === []) {
            $this->minFrequency = 0;

            return;
        }

        $minimum = null;

        foreach ($this->frequencies as $frequency => $bucket) {
            if ($bucket->isEmpty()) {
                continue;
            }

            if ($minimum === null || $frequency < $minimum) {
                $minimum = $frequency;
            }
        }

        $this->minFrequency = $minimum ?? 0;
    }
}
