<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Foundation;

use AlgorithmsLab\Foundation\BitArray;
use InvalidArgumentException;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;

final class BitArrayTest extends TestCase
{
    public function testStoresAndReadsBits(): void
    {
        $bits = new BitArray(16);
        $bits->set(0);
        $bits->set(7);
        $bits->set(8);
        $bits->set(15);

        self::assertTrue($bits->get(0));
        self::assertTrue($bits->get(7));
        self::assertTrue($bits->get(8));
        self::assertTrue($bits->get(15));
        self::assertFalse($bits->get(1));
        self::assertFalse($bits->get(14));
        self::assertSame(4, $bits->popcount());
    }

    public function testCanClearIndividualBits(): void
    {
        $bits = new BitArray(8);
        $bits->set(3);
        $bits->set(3, false);

        self::assertFalse($bits->get(3));
        self::assertSame(0, $bits->popcount());
    }

    public function testClearResetsAllBits(): void
    {
        $bits = new BitArray(20);
        $bits->set(0);
        $bits->set(19);

        $bits->clear();

        self::assertSame(0, $bits->popcount());
        self::assertSame(3, strlen($bits->toBytes()));
    }

    public function testZeroSizedArrayHasNoBits(): void
    {
        $bits = new BitArray(0);

        self::assertSame(0, $bits->size());
        self::assertSame(0, $bits->popcount());
        self::assertSame('', $bits->toBytes());
    }

    public function testRejectsNegativeSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BitArray(-1);
    }

    public function testRejectsOutOfRangeIndexes(): void
    {
        $bits = new BitArray(8);

        $this->expectException(OutOfRangeException::class);

        $bits->get(8);
    }

    public function testRejectsNegativeIndexes(): void
    {
        $bits = new BitArray(8);

        $this->expectException(OutOfRangeException::class);

        $bits->set(-1);
    }
}
