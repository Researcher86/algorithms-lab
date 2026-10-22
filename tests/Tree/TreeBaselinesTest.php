<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Tree;

use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Tree\PhpArrayIndex;
use AlgorithmsLab\Tree\SortedArrayIndex;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TreeBaselinesTest extends TestCase
{
    public function testSortedArrayIndexSupportsSearchInsertAndDelete(): void
    {
        $counters = new Counters();
        $index = new SortedArrayIndex($counters);

        foreach ([10, 20, 5, 6] as $key) {
            self::assertTrue($index->insert($key));
        }

        self::assertFalse($index->insert(10));
        self::assertSame([5, 6, 10, 20], $index->toSortedArray());
        self::assertTrue($index->search(6));
        self::assertFalse($index->search(7));
        self::assertTrue($index->delete(10));
        self::assertFalse($index->delete(10));
        self::assertSame(3, $index->count());

        $counters->reset();
        self::assertTrue($index->search(20));
        self::assertGreaterThan(0, $counters->comparisons);
    }

    public function testSortedArrayIndexCanBeBuiltFromSortedKeys(): void
    {
        $index = SortedArrayIndex::fromSortedArray([1, 3, 5, 9]);

        self::assertSame([1, 3, 5, 9], $index->toSortedArray());
        self::assertTrue($index->search(5));
        self::assertFalse($index->search(4));
    }

    public function testSortedArrayIndexRejectsUnsortedInitialKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SortedArrayIndex::fromSortedArray([1, 3, 2]);
    }

    public function testPhpArrayIndexSupportsSearchInsertAndDelete(): void
    {
        $index = new PhpArrayIndex();

        foreach ([10, 20, 5, 6] as $key) {
            self::assertTrue($index->insert($key));
        }

        self::assertFalse($index->insert(10));
        self::assertSame([5, 6, 10, 20], $index->toSortedArray());
        self::assertTrue($index->search(6));
        self::assertFalse($index->search(7));
        self::assertTrue($index->delete(10));
        self::assertFalse($index->delete(10));
        self::assertSame(3, $index->count());
    }
}
