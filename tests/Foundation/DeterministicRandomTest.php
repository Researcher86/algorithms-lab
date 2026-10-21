<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Foundation;

use AlgorithmsLab\Foundation\DeterministicRandom;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeterministicRandomTest extends TestCase
{
    private const MAX = 1_152_921_504_606_846_975;

    public function testSameSeedProducesSameSequence(): void
    {
        $left = new DeterministicRandom('dataset-42');
        $right = new DeterministicRandom('dataset-42');

        for ($i = 0; $i < 20; $i++) {
            self::assertSame($left->nextInt(), $right->nextInt());
        }
    }

    public function testDifferentSeedsProduceDifferentSequences(): void
    {
        $left = new DeterministicRandom('left');
        $right = new DeterministicRandom('right');

        self::assertNotSame($left->nextInt(), $right->nextInt());
    }

    public function testNextIntStaysInItsValueSpace(): void
    {
        $random = new DeterministicRandom('range');

        for ($i = 0; $i < 1_000; $i++) {
            $value = $random->nextInt();

            self::assertGreaterThanOrEqual(0, $value);
            self::assertLessThanOrEqual(self::MAX, $value);
        }
    }

    public function testNextFloatStaysInTheUnitInterval(): void
    {
        $random = new DeterministicRandom('float');

        for ($i = 0; $i < 1_000; $i++) {
            $value = $random->nextFloat();

            self::assertGreaterThanOrEqual(0.0, $value);
            self::assertLessThan(1.0, $value);
        }
    }

    public function testIntBetweenStaysWithinBounds(): void
    {
        $random = new DeterministicRandom('bounds');

        for ($i = 0; $i < 1_000; $i++) {
            $value = $random->intBetween(10, 20);

            self::assertGreaterThanOrEqual(10, $value);
            self::assertLessThanOrEqual(20, $value);
        }
    }

    public function testSequenceAdvancesWithEveryCall(): void
    {
        $random = new DeterministicRandom('advance');

        self::assertNotSame($random->nextInt(), $random->nextInt());
    }

    /**
     * @param int $min
     * @param int $max
     */
    #[DataProvider('invalidRanges')]
    public function testIntBetweenRejectsInvalidRanges(int $min, int $max): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeterministicRandom('invalid')->intBetween($min, $max);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidRanges(): iterable
    {
        yield 'negative min' => [-1, 10];
        yield 'max below min' => [10, 9];
        yield 'max outside the value space' => [0, 1_152_921_504_606_846_976];
    }
}
