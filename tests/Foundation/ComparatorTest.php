<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Foundation;

use AlgorithmsLab\Foundation\Comparator;
use PHPUnit\Framework\TestCase;

final class ComparatorTest extends TestCase
{
    public function testIntegerComparatorOrdersValues(): void
    {
        $comparator = Comparator::integers();

        self::assertLessThan(0, $comparator->compare(1, 2));
        self::assertSame(0, $comparator->compare(2, 2));
        self::assertGreaterThan(0, $comparator->compare(3, 2));
    }

    public function testCountsEveryComparisonAndResets(): void
    {
        $comparator = Comparator::integers();

        $comparator->compare(1, 2);
        $comparator->compare(2, 3);

        self::assertSame(2, $comparator->comparisons());

        $comparator->reset();

        self::assertSame(0, $comparator->comparisons());
    }

    public function testAcceptsACustomClosure(): void
    {
        $comparator = Comparator::of(
            static fn (mixed $left, mixed $right): int => strlen((string) $left) <=> strlen((string) $right),
        );

        self::assertLessThan(0, $comparator->compare('a', 'abc'));
        self::assertSame(0, $comparator->compare('abc', 'xyz'));
        self::assertSame(2, $comparator->comparisons());
    }
}
