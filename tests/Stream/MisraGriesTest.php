<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Stream;

use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Stream\MisraGries;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MisraGriesTest extends TestCase
{
    public function testCancellationKeepsTheSetBounded(): void
    {
        $misraGries = new MisraGries(2);

        foreach (['a', 'b', 'c'] as $key) {
            $misraGries->observe($key);
        }

        self::assertSame(0, $misraGries->size());
        self::assertSame([], $misraGries->top(2));
    }

    public function testADroppedKeyDoesNotGrowTheCounterSet(): void
    {
        $misraGries = new MisraGries(2);
        $misraGries->observe('a', 5);
        $misraGries->observe('b', 5);
        $misraGries->observe('c');

        self::assertSame(2, $misraGries->size());
        self::assertSame(0, $misraGries->estimate('c'));
        self::assertSame([['key' => 'a', 'count' => 4], ['key' => 'b', 'count' => 4]], $misraGries->top(2));
    }

    public function testCountersAreNeverAboveTheTrueFrequency(): void
    {
        $misraGries = new MisraGries(3);
        $trueCounts = [];

        foreach (['a', 'b', 'a', 'c', 'a', 'b', 'd', 'e', 'a', 'f'] as $key) {
            $misraGries->observe($key);
            $trueCounts[$key] = ($trueCounts[$key] ?? 0) + 1;
        }

        foreach ($trueCounts as $key => $count) {
            self::assertLessThanOrEqual($count, $misraGries->estimate($key));
        }

        self::assertLessThanOrEqual(3, $misraGries->size());
    }

    public function testCountersRecordCancellationScans(): void
    {
        $counters = new Counters();
        $misraGries = new MisraGries(2, $counters);
        $misraGries->observe('a');
        $misraGries->observe('b');
        $misraGries->observe('c');

        self::assertGreaterThan(0, $counters->comparisons);
    }

    public function testRejectsInvalidConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MisraGries(0);
    }

    public function testRejectsNonPositiveIncrement(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MisraGries(2)->observe('a', 0);
    }
}
