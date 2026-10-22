<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Foundation;

use AlgorithmsLab\Foundation\UnionFind;
use AlgorithmsLab\Instrumentation\Counters;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;

final class UnionFindTest extends TestCase
{
    public function testUnionsElementsAndTracksComponentSize(): void
    {
        $sets = new UnionFind();

        for ($i = 0; $i < 5; ++$i) {
            $sets->makeSet($i);
        }

        self::assertSame(5, $sets->setCount());
        self::assertTrue($sets->union(0, 1));
        self::assertTrue($sets->union(1, 2));
        self::assertFalse($sets->union(0, 1));

        self::assertTrue($sets->connected(0, 2));
        self::assertFalse($sets->connected(0, 3));
        self::assertSame(3, $sets->componentSize(2));
        self::assertSame(1, $sets->componentSize(3));
        self::assertSame(3, $sets->setCount());
    }

    public function testMakeSetIsIdempotent(): void
    {
        $sets = new UnionFind();
        $sets->makeSet(7);
        $sets->makeSet(7);

        self::assertSame(1, $sets->setCount());
        self::assertSame(1, $sets->componentSize(7));
    }

    public function testFindCompressesThePath(): void
    {
        $sets = new UnionFind();

        for ($i = 0; $i < 4; ++$i) {
            $sets->makeSet($i);
        }

        $sets->union(0, 1);
        $sets->union(1, 2);
        $sets->union(2, 3);

        self::assertSame($sets->find(0), $sets->find(3));
    }

    public function testUnknownElementsAreRejected(): void
    {
        $this->expectException(OutOfRangeException::class);

        new UnionFind()->find(1);
    }

    public function testVariantsKeepTheSameConnectivity(): void
    {
        $variants = [
            new UnionFind(),
            new UnionFind(pathCompression: false),
            new UnionFind(unionByRank: false),
            new UnionFind(pathCompression: false, unionByRank: false),
        ];

        foreach ($variants as $sets) {
            for ($i = 0; $i < 10; ++$i) {
                $sets->makeSet($i);
            }

            $sets->union(0, 1);
            $sets->union(1, 2);
            $sets->union(3, 4);
            $sets->union(4, 5);
            $sets->union(2, 5);
        }

        foreach ($variants as $sets) {
            self::assertSame(5, $sets->setCount());
            self::assertTrue($sets->connected(0, 3));
            self::assertFalse($sets->connected(0, 6));
            self::assertSame(6, $sets->componentSize(5));
        }
    }

    public function testCountersRecordParentReads(): void
    {
        $counters = new Counters();
        $sets = new UnionFind(counters: $counters);

        for ($i = 0; $i < 10; ++$i) {
            $sets->makeSet($i);
        }

        for ($i = 1; $i < 10; ++$i) {
            $sets->union(0, $i);
        }

        $sets->find(9);

        self::assertGreaterThan(0, $counters->nodeReads);
    }
}
