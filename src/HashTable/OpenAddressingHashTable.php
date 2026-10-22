<?php

declare(strict_types=1);

namespace AlgorithmsLab\HashTable;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;
use RuntimeException;

/**
 * Open addressing with linear, quadratic or double-hash probing.
 *
 * An empty slot ends an unsuccessful search; a tombstone does not. Insertions
 * remember the first tombstone but keep probing, because an equal key might
 * still be further along the sequence. That is the detail separate chaining
 * does not have to think about.
 */
final class OpenAddressingHashTable implements HashTable
{
    private const EMPTY = 0;
    private const OCCUPIED = 1;
    private const TOMBSTONE = 2;

    private int $capacity;

    /** @var array<int, string> */
    private array $keys;

    /** @var array<int, mixed> */
    private array $values;

    /** @var array<int, int> */
    private array $states;

    private int $size = 0;

    private int $tombstones = 0;

    private readonly HashFunction $hashFunction;

    private readonly Counters $counters;

    private readonly ProbeStrategy $strategy;

    private readonly float $maxLoadFactor;

    public function __construct(
        int $capacity = 16,
        ProbeStrategy $strategy = ProbeStrategy::Linear,
        ?HashFunction $hashFunction = null,
        ?Counters $counters = null,
        float $maxLoadFactor = 0.75,
    ) {
        if ($capacity < 1) {
            throw new InvalidArgumentException('Capacity must be at least 1');
        }

        if ($maxLoadFactor <= 0.0 || $maxLoadFactor > 1.0) {
            throw new InvalidArgumentException('Max load factor must be in (0, 1]');
        }

        $this->capacity = self::nextPowerOfTwo($capacity);
        $this->strategy = $strategy;
        $this->hashFunction = $hashFunction ?? HashFunction::fnv1a64();
        $this->counters = $counters ?? new Counters();
        $this->maxLoadFactor = $maxLoadFactor;
        $this->keys = array_fill(0, $this->capacity, '');
        $this->values = array_fill(0, $this->capacity, null);
        $this->states = array_fill(0, $this->capacity, self::EMPTY);
    }

    public function put(string $key, mixed $value): void
    {
        if (($this->size + $this->tombstones + 1) / $this->capacity > $this->maxLoadFactor) {
            $this->resize($this->capacity * 2);
        }

        $probesBefore = $this->counters->probes;
        $slot = $this->findSlot($key, true);

        if ($slot < 0) {
            $this->resize($this->capacity * 2);
            $probesBefore = $this->counters->probes;
            $slot = $this->findSlot($key, true);
        }

        if ($slot < 0) {
            throw new RuntimeException('Open-addressing table is full and could not grow');
        }

        if ($this->states[$slot] === self::OCCUPIED) {
            $this->values[$slot] = $value;

            return;
        }

        if ($this->states[$slot] === self::TOMBSTONE) {
            --$this->tombstones;
        }

        $this->keys[$slot] = $key;
        $this->values[$slot] = $value;
        $this->states[$slot] = self::OCCUPIED;
        ++$this->size;

        if ($this->counters->probes - $probesBefore > 1) {
            ++$this->counters->collisions;
        }
    }

    public function get(string $key): mixed
    {
        $slot = $this->findSlot($key, false);

        return $slot < 0 ? null : $this->values[$slot];
    }

    public function has(string $key): bool
    {
        return $this->findSlot($key, false) >= 0;
    }

    public function remove(string $key): bool
    {
        $slot = $this->findSlot($key, false);

        if ($slot < 0) {
            return false;
        }

        $this->states[$slot] = self::TOMBSTONE;
        $this->keys[$slot] = '';
        $this->values[$slot] = null;
        --$this->size;
        ++$this->tombstones;

        return true;
    }

    public function count(): int
    {
        return $this->size;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function loadFactor(): float
    {
        return $this->size / $this->capacity;
    }

    public function tombstoneRatio(): float
    {
        return $this->tombstones / $this->capacity;
    }

    public function strategy(): ProbeStrategy
    {
        return $this->strategy;
    }

    public function counters(): Counters
    {
        return $this->counters;
    }

    public function resize(int $capacity): void
    {
        $capacity = self::nextPowerOfTwo($capacity);

        if ($capacity < $this->size) {
            throw new InvalidArgumentException('New capacity cannot be smaller than the number of entries');
        }

        $oldKeys = $this->keys;
        $oldValues = $this->values;
        $oldStates = $this->states;
        $oldCapacity = $this->capacity;

        $this->capacity = $capacity;
        $this->keys = array_fill(0, $capacity, '');
        $this->values = array_fill(0, $capacity, null);
        $this->states = array_fill(0, $capacity, self::EMPTY);
        $this->size = 0;
        $this->tombstones = 0;
        ++$this->counters->rehashes;

        for ($index = 0; $index < $oldCapacity; ++$index) {
            if ($oldStates[$index] !== self::OCCUPIED) {
                continue;
            }

            $slot = $this->findSlot($oldKeys[$index], true);

            if ($slot < 0) {
                throw new RuntimeException('Rehash could not find a free slot');
            }

            $this->keys[$slot] = $oldKeys[$index];
            $this->values[$slot] = $oldValues[$index];
            $this->states[$slot] = self::OCCUPIED;
            ++$this->size;
        }
    }

    public function clear(): void
    {
        $this->keys = array_fill(0, $this->capacity, '');
        $this->values = array_fill(0, $this->capacity, null);
        $this->states = array_fill(0, $this->capacity, self::EMPTY);
        $this->size = 0;
        $this->tombstones = 0;
        $this->counters->reset();
    }

    private function findSlot(string $key, bool $forInsert): int
    {
        ++$this->counters->hashCalls;
        $hash = $this->hashFunction->hash($key);
        $start = $hash % $this->capacity;
        $step = $this->stepFor($hash);
        $firstTombstone = -1;

        for ($probe = 0; $probe < $this->capacity; ++$probe) {
            $index = $this->slotFor($start, $probe, $step);
            ++$this->counters->probes;
            $state = $this->states[$index];

            if ($state === self::OCCUPIED) {
                ++$this->counters->comparisons;

                if ($this->keys[$index] === $key) {
                    return $index;
                }

                continue;
            }

            if ($state === self::TOMBSTONE) {
                if ($forInsert && $firstTombstone < 0) {
                    $firstTombstone = $index;
                }

                continue;
            }

            return $forInsert ? ($firstTombstone >= 0 ? $firstTombstone : $index) : -1;
        }

        return $forInsert ? $firstTombstone : -1;
    }

    private function slotFor(int $start, int $probe, int $step): int
    {
        return match ($this->strategy) {
            ProbeStrategy::Linear => ($start + $probe) % $this->capacity,
            ProbeStrategy::Quadratic => ($start + intdiv($probe * ($probe + 1), 2)) % $this->capacity,
            ProbeStrategy::DoubleHashing => ($start + $probe * $step) % $this->capacity,
        };
    }

    private function stepFor(int $hash): int
    {
        if ($this->strategy !== ProbeStrategy::DoubleHashing || $this->capacity === 1) {
            return 1;
        }

        $step = (($hash >> 32) | 1) & ($this->capacity - 1);

        return $step === 0 ? 1 : $step;
    }

    private static function nextPowerOfTwo(int $value): int
    {
        $power = 1;

        while ($power < $value) {
            if ($power > intdiv(PHP_INT_MAX, 2)) {
                throw new InvalidArgumentException('Capacity is too large');
            }

            $power *= 2;
        }

        return $power;
    }
}
