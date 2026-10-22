<?php

declare(strict_types=1);

namespace AlgorithmsLab\BloomFilter;

use AlgorithmsLab\Foundation\BitArray;
use AlgorithmsLab\Foundation\Hashers;
use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * A Bloom filter sized from the expected item count and a target false
 * positive rate.
 *
 * The two conventional formulas choose the bit count m and hash count k:
 *
 *   m = -n ln(p) / (ln 2)^2
 *   k = (m / n) ln 2
 *
 * Double hashing derives the k positions from two independent hashes. Inserted
 * items are counted, but the set itself cannot enumerate them and cannot
 * delete them; false positives are the price of that compactness.
 */
final class BloomFilter
{
    private readonly BitArray $bits;

    private readonly int $bitCount;

    private readonly int $hashCount;

    private readonly Counters $counters;

    private int $inserted = 0;

    public function __construct(
        private readonly int $expectedItems,
        private readonly float $targetFalsePositiveRate,
        ?Counters $counters = null,
    ) {
        if ($expectedItems < 1) {
            throw new InvalidArgumentException('Expected item count must be at least 1');
        }

        if ($targetFalsePositiveRate <= 0.0 || $targetFalsePositiveRate >= 1.0) {
            throw new InvalidArgumentException('False positive rate must be in (0, 1)');
        }

        $this->bitCount = self::optimalBits($expectedItems, $targetFalsePositiveRate);
        $this->hashCount = self::optimalHashCount($this->bitCount, $expectedItems);
        $this->bits = new BitArray($this->bitCount);
        $this->counters = $counters ?? new Counters();
    }

    public static function optimalBits(int $expectedItems, float $falsePositiveRate): int
    {
        if ($expectedItems < 1 || $falsePositiveRate <= 0.0 || $falsePositiveRate >= 1.0) {
            throw new InvalidArgumentException('Need a positive item count and a rate in (0, 1)');
        }

        $bits = -($expectedItems * log($falsePositiveRate)) / (log(2.0) ** 2);

        return max(1, (int) ceil($bits));
    }

    public static function optimalHashCount(int $bitCount, int $expectedItems): int
    {
        if ($bitCount < 1 || $expectedItems < 1) {
            throw new InvalidArgumentException('Bit and item counts must be positive');
        }

        return max(1, (int) round(($bitCount / $expectedItems) * log(2.0)));
    }

    public function add(string $key): void
    {
        ++$this->inserted;

        foreach ($this->positions($key) as $position) {
            ++$this->counters->probes;
            $this->bits->set($position);
        }
    }

    public function mightContain(string $key): bool
    {
        foreach ($this->positions($key) as $position) {
            ++$this->counters->probes;

            if (!$this->bits->get($position)) {
                return false;
            }
        }

        return true;
    }

    public function expectedItems(): int
    {
        return $this->expectedItems;
    }

    public function targetFalsePositiveRate(): float
    {
        return $this->targetFalsePositiveRate;
    }

    public function bitCount(): int
    {
        return $this->bitCount;
    }

    public function hashCount(): int
    {
        return $this->hashCount;
    }

    public function inserted(): int
    {
        return $this->inserted;
    }

    public function bitDensity(): float
    {
        return $this->bits->popcount() / $this->bitCount;
    }

    /**
     * The rate the current fill predicts, not the rate that was requested.
     */
    public function estimatedFalsePositiveRate(): float
    {
        if ($this->inserted === 0) {
            return 0.0;
        }

        return (1.0 - exp(-($this->hashCount * $this->inserted) / $this->bitCount)) ** $this->hashCount;
    }

    public function counters(): Counters
    {
        return $this->counters;
    }

    public function clear(): void
    {
        $this->bits->clear();
        $this->inserted = 0;
        $this->counters->reset();
    }

    /**
     * @return list<int>
     */
    private function positions(string $key): array
    {
        $this->counters->hashCalls += 2;
        $step = Hashers::xxh128($key) % $this->bitCount;

        if ($step === 0) {
            $step = 1;
        }

        $position = Hashers::fnv1a64($key) % $this->bitCount;
        $positions = [];

        for ($i = 0; $i < $this->hashCount; ++$i) {
            $positions[] = $position;
            $position = ($position + $step) % $this->bitCount;
        }

        return $positions;
    }
}
