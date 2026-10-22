<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\BloomFilter;

use AlgorithmsLab\BloomFilter\BloomFilter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BloomFilterTest extends TestCase
{
    public function testSizesItselfFromTheExpectedItemsAndRate(): void
    {
        $filter = new BloomFilter(1000, 0.01);

        self::assertGreaterThan(9000, $filter->bitCount());
        self::assertLessThan(10_000, $filter->bitCount());
        self::assertGreaterThanOrEqual(6, $filter->hashCount());
        self::assertLessThanOrEqual(8, $filter->hashCount());
        self::assertSame(1000, $filter->expectedItems());
        self::assertSame(0.01, $filter->targetFalsePositiveRate());
    }

    public function testHasNoFalseNegatives(): void
    {
        $filter = new BloomFilter(1000, 0.01);

        for ($i = 0; $i < 1000; ++$i) {
            $filter->add('present-' . $i);
        }

        for ($i = 0; $i < 1000; ++$i) {
            self::assertTrue($filter->mightContain('present-' . $i));
        }
    }

    public function testMeasuredFalsePositiveRateStaysNearTheTarget(): void
    {
        $filter = new BloomFilter(2000, 0.05);

        for ($i = 0; $i < 2000; ++$i) {
            $filter->add('present-' . $i);
        }

        $falsePositives = 0;
        $queries = 10_000;

        for ($i = 0; $i < $queries; ++$i) {
            if ($filter->mightContain('absent-' . $i)) {
                ++$falsePositives;
            }
        }

        self::assertLessThan(0.10, $falsePositives / $queries);
        self::assertGreaterThan(0.0, $filter->inserted());
        self::assertGreaterThan(0.0, $filter->bitDensity());
        self::assertGreaterThan(0.01, $filter->estimatedFalsePositiveRate());
    }

    public function testAcceptsADeterministicRate(): void
    {
        $filter = new BloomFilter(100, 0.1);
        $filter->add('key');

        self::assertTrue($filter->mightContain('key'));
        self::assertSame(1, $filter->inserted());
        self::assertSame(4, $filter->counters()->hashCalls);
        self::assertGreaterThanOrEqual($filter->hashCount(), $filter->counters()->probes);
    }

    public function testClearResetsBitsAndCounters(): void
    {
        $filter = new BloomFilter(100, 0.1);
        $filter->add('key');
        $filter->clear();

        self::assertSame(0, $filter->inserted());
        self::assertSame(0.0, $filter->bitDensity());
        self::assertSame(0, $filter->counters()->hashCalls);
    }

    public function testOptimalFormulasExposeTheirInputs(): void
    {
        self::assertSame(9586, BloomFilter::optimalBits(1000, 0.01));
        self::assertSame(7, BloomFilter::optimalHashCount(9586, 1000));
    }

    public function testRejectsInvalidConstructionParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BloomFilter(0, 0.01);
    }

    public function testRejectsInvalidFalsePositiveRates(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BloomFilter(100, 1.0);
    }

    public function testRejectsInvalidOptimalInputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BloomFilter::optimalBits(0, 0.01);
    }
}
