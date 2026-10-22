<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Tree;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Tree\BTree;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BTreeTest extends TestCase
{
    public function testEmptyTree(): void
    {
        $tree = new BTree(3);

        self::assertSame(0, $tree->count());
        self::assertSame(1, $tree->height());
        self::assertFalse($tree->search(1));
        self::assertSame([], $tree->toSortedArray());
        $tree->validate();
    }

    public function testInsertAndSearch(): void
    {
        $tree = new BTree(3);

        foreach ([10, 20, 5, 6, 12, 30, 7, 17] as $key) {
            self::assertTrue($tree->insert($key));
        }

        self::assertFalse($tree->insert(12));
        self::assertSame(8, $tree->count());
        self::assertSame([5, 6, 7, 10, 12, 17, 20, 30], $tree->toSortedArray());

        foreach ([5, 6, 7, 10, 12, 17, 20, 30] as $key) {
            self::assertTrue($tree->search($key));
        }

        self::assertFalse($tree->search(8));
        $tree->validate();
    }

    public function testRootSplitAndHeight(): void
    {
        $tree = new BTree(2);

        foreach (range(1, 20) as $key) {
            $tree->insert($key);
        }

        self::assertSame(20, $tree->count());
        self::assertGreaterThan(1, $tree->height());
        self::assertSame(range(1, 20), $tree->toSortedArray());
        $tree->validate();
    }

    public function testDeleteLeafAndInternalKeys(): void
    {
        $tree = new BTree(3);

        foreach (range(1, 40) as $key) {
            $tree->insert($key);
        }

        foreach ([1, 2, 3, 15, 16, 17, 39, 40] as $key) {
            self::assertTrue($tree->delete($key), sprintf('key %d', $key));
            $tree->validate();
        }

        self::assertFalse($tree->delete(100));
        self::assertSame(32, $tree->count());

        foreach ([1, 2, 3, 15, 16, 17, 39, 40] as $key) {
            self::assertFalse($tree->search($key));
        }
    }

    public function testDeleteAllKeysInAscendingOrder(): void
    {
        $tree = new BTree(2);

        foreach (range(1, 100) as $key) {
            $tree->insert($key);
        }

        foreach (range(1, 100) as $key) {
            self::assertTrue($tree->delete($key));
        }

        self::assertSame(0, $tree->count());
        self::assertSame([], $tree->toSortedArray());
        self::assertSame(1, $tree->height());
        $tree->validate();
    }

    public function testDeleteRandomizedAgainstSortedArray(): void
    {
        foreach ([2, 3, 4, 8] as $degree) {
            $tree = new BTree($degree);
            $expected = [];
            $random = new DeterministicRandom('btree-' . $degree);

            for ($operation = 0; $operation < 800; ++$operation) {
                $key = $random->intBetween(0, 250);

                if (($random->nextInt() & 1) === 0) {
                    $inserted = $tree->insert($key);

                    if (isset($expected[$key])) {
                        self::assertFalse($inserted);
                    } else {
                        self::assertTrue($inserted);
                        $expected[$key] = true;
                    }
                } else {
                    $deleted = $tree->delete($key);

                    if (isset($expected[$key])) {
                        self::assertTrue($deleted);
                        unset($expected[$key]);
                    } else {
                        self::assertFalse($deleted);
                    }
                }

                if (($operation % 50) === 0) {
                    $tree->validate();
                }
            }

            $keys = array_keys($expected);
            sort($keys);

            self::assertSame($keys, $tree->toSortedArray());
            self::assertSame(count($keys), $tree->count());
            $tree->validate();
        }
    }

    public function testCountersRecordSearchWork(): void
    {
        $counters = new Counters();
        $tree = new BTree(4, $counters);

        foreach (range(1, 100) as $key) {
            $tree->insert($key);
        }

        $counters->reset();
        self::assertTrue($tree->search(50));
        self::assertGreaterThan(0, $counters->nodeReads);
        self::assertGreaterThan(0, $counters->comparisons);
    }

    public function testRejectsDegreeBelowTwo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BTree(1);
    }
}
