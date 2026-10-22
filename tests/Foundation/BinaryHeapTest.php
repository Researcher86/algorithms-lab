<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Foundation;

use AlgorithmsLab\Foundation\BinaryHeap;
use AlgorithmsLab\Foundation\Comparator;
use PHPUnit\Framework\TestCase;
use UnderflowException;

final class BinaryHeapTest extends TestCase
{
    public function testMinHeapExtractsInAscendingOrder(): void
    {
        $heap = new BinaryHeap(Comparator::integers());

        foreach ([5, 3, 8, 1, 9, 2] as $value) {
            $heap->insert($value);
        }

        $values = [];

        while (!$heap->isEmpty()) {
            $values[] = $heap->extract();
        }

        self::assertSame([1, 2, 3, 5, 8, 9], $values);
    }

    public function testMaxHeapExtractsInDescendingOrder(): void
    {
        $heap = new BinaryHeap(Comparator::integers(), false);

        foreach ([5, 3, 8, 1, 9, 2] as $value) {
            $heap->insert($value);
        }

        $values = [];

        while (!$heap->isEmpty()) {
            $values[] = $heap->extract();
        }

        self::assertSame([9, 8, 5, 3, 2, 1], $values);
    }

    public function testPeekReturnsTheRootWithoutRemovingIt(): void
    {
        $heap = new BinaryHeap(Comparator::integers());
        $heap->insert(4);
        $heap->insert(2);

        self::assertSame(2, $heap->peek());
        self::assertSame(2, $heap->count());
        self::assertSame(2, $heap->extract());
    }

    public function testDuplicatesAreKept(): void
    {
        $heap = new BinaryHeap(Comparator::integers());

        foreach ([3, 3, 1, 1, 2] as $value) {
            $heap->insert($value);
        }

        $values = [];

        while (!$heap->isEmpty()) {
            $values[] = $heap->extract();
        }

        self::assertSame([1, 1, 2, 3, 3], $values);
    }

    public function testHeapUsesTheComparatorForEveryOrderingDecision(): void
    {
        $comparator = Comparator::integers();
        $heap = new BinaryHeap($comparator);
        $heap->insert(3);
        $heap->insert(1);
        $heap->insert(2);

        self::assertGreaterThan(0, $comparator->comparisons());
    }

    public function testClearRemovesEverything(): void
    {
        $heap = new BinaryHeap(Comparator::integers());
        $heap->insert(1);
        $heap->clear();

        self::assertTrue($heap->isEmpty());
        self::assertSame(0, $heap->count());
    }

    public function testPeekOnEmptyHeapThrows(): void
    {
        $this->expectException(UnderflowException::class);

        new BinaryHeap(Comparator::integers())->peek();
    }

    public function testExtractOnEmptyHeapThrows(): void
    {
        $this->expectException(UnderflowException::class);

        new BinaryHeap(Comparator::integers())->extract();
    }
}
