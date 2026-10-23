<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Stream;

use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Stream\SpaceSaving;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SpaceSavingTest extends TestCase
{
    public function testKeepsAtMostCapacityCounters(): void
    {
        $spaceSaving = new SpaceSaving(2);

        foreach (['a', 'b', 'a', 'c', 'c', 'c'] as $key) {
            $spaceSaving->observe($key);
        }

        self::assertSame(2, $spaceSaving->size());
        self::assertSame(2, $spaceSaving->capacity());
        self::assertSame([
            ['key' => 'c', 'count' => 4],
            ['key' => 'a', 'count' => 2],
        ], $spaceSaving->top(2));
        self::assertSame(2, $spaceSaving->estimate('a'));
        self::assertGreaterThan(0, $spaceSaving->estimate('c'));
        self::assertSame(0, $spaceSaving->estimate('missing'));
    }

    public function testReplacementInheritsTheMinimum(): void
    {
        $spaceSaving = new SpaceSaving(1);
        $spaceSaving->observe('a', 5);
        $spaceSaving->observe('b', 2);

        self::assertSame(0, $spaceSaving->estimate('a'));
        self::assertSame(7, $spaceSaving->estimate('b'));
        self::assertSame(1, $spaceSaving->size());
    }

    public function testCountersRecordMinimumScans(): void
    {
        $counters = new Counters();
        $spaceSaving = new SpaceSaving(2, $counters);
        $spaceSaving->observe('a');
        $spaceSaving->observe('b');
        $spaceSaving->observe('c');

        self::assertGreaterThan(0, $counters->comparisons);
    }

    public function testTopClampsToTheAvailableKeys(): void
    {
        $spaceSaving = new SpaceSaving(5);
        $spaceSaving->observe('a');
        $spaceSaving->observe('b');

        self::assertCount(2, $spaceSaving->top(10));
        self::assertSame([], $spaceSaving->top(0));
        self::assertSame(['a' => 1, 'b' => 1], $spaceSaving->counters());
    }

    public function testRejectsInvalidConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpaceSaving(0);
    }

    public function testRejectsNonPositiveIncrement(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpaceSaving(2)->observe('a', 0);
    }
}
