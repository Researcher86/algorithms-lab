<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Tree;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Tree\BPlusTree;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BPlusTreeTest extends TestCase
{
    public function testEmptyTree(): void
    {
        $tree = new BPlusTree(3);

        self::assertSame(0, $tree->count());
        self::assertSame(1, $tree->height());
        self::assertFalse($tree->search(1));
        self::assertSame([], $tree->range(1, 10));
        self::assertSame([], $tree->toSortedArray());
        $tree->validate();
    }

    public function testInsertSearchAndRange(): void
    {
        $tree = new BPlusTree(2);

        foreach ([10, 20, 5, 6, 12, 30, 7, 17] as $key) {
            self::assertTrue($tree->insert($key));
        }

        self::assertFalse($tree->insert(12));
        self::assertSame(8, $tree->count());
        self::assertSame([5, 6, 7, 10, 12, 17, 20, 30], $tree->toSortedArray());
        self::assertSame([6, 7, 10, 12], $tree->range(6, 12));
        self::assertSame([], $tree->range(12, 6));
        self::assertSame([5, 6, 7, 10, 12, 17, 20, 30], $tree->range(0, 100));

        foreach ([5, 6, 7, 10, 12, 17, 20, 30] as $key) {
            self::assertTrue($tree->search($key));
        }

        self::assertFalse($tree->search(8));
        self::assertGreaterThan(1, $tree->height());
        $tree->validate();
    }

    public function testLinkedLeavesAreInOrderAfterSplits(): void
    {
        $tree = new BPlusTree(2);

        foreach (range(1, 100) as $key) {
            $tree->insert($key);
        }

        self::assertSame(range(1, 100), $tree->toSortedArray());
        self::assertSame(range(25, 75), $tree->range(25, 75));
        $tree->validate();
    }

    public function testRandomizedInsertAndRangeAgainstSortedArray(): void
    {
        foreach ([2, 3, 4, 8] as $degree) {
            $tree = new BPlusTree($degree);
            $expected = [];
            $random = new DeterministicRandom('bplus-' . $degree);

            for ($operation = 0; $operation < 600; ++$operation) {
                $key = $random->intBetween(0, 300);

                if (($random->nextInt() & 1) === 0) {
                    $inserted = $tree->insert($key);

                    if (isset($expected[$key])) {
                        self::assertFalse($inserted);
                    } else {
                        self::assertTrue($inserted);
                        $expected[$key] = true;
                    }
                } else {
                    self::assertSame(isset($expected[$key]), $tree->search($key));
                }
            }

            $keys = array_keys($expected);
            sort($keys);

            self::assertSame($keys, $tree->toSortedArray());
            self::assertSame(count($keys), $tree->count());

            for ($query = 0; $query < 100; ++$query) {
                $from = $random->intBetween(0, 300);
                $to = $random->intBetween($from, 300);
                $wanted = array_values(array_filter(
                    $keys,
                    static fn (int $key): bool => $key >= $from && $key <= $to,
                ));

                self::assertSame($wanted, $tree->range($from, $to));
            }

            $tree->validate();
        }
    }

    public function testBulkLoadBuildsAFullTree(): void
    {
        $keys = range(1, 1_000);
        $tree = BPlusTree::bulkLoad(4, $keys);

        self::assertSame(1_000, $tree->count());
        self::assertSame($keys, $tree->toSortedArray());
        self::assertSame(range(500, 510), $tree->range(500, 510));
        self::assertTrue($tree->search(1));
        self::assertTrue($tree->search(1_000));
        self::assertFalse($tree->search(1_001));
        $tree->validate(false);
    }

    public function testBulkLoadRejectsUnsortedOrDuplicateKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BPlusTree::bulkLoad(3, [1, 3, 2]);
    }

    public function testBulkLoadRejectsDuplicateKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BPlusTree::bulkLoad(3, [1, 2, 2]);
    }

    public function testCountersRecordSearchAndRangeWork(): void
    {
        $counters = new Counters();
        $tree = BPlusTree::bulkLoad(4, range(1, 1_000), $counters);
        $counters->reset();

        self::assertTrue($tree->search(500));
        self::assertGreaterThan(0, $counters->nodeReads);

        $counters->reset();
        self::assertCount(101, $tree->range(200, 300));
        self::assertGreaterThan(0, $counters->nodeReads);
    }

    public function testRejectsDegreeBelowTwo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BPlusTree(1);
    }
}
