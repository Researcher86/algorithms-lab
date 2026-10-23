<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Stream;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Stream\TopKHeap;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TopKHeapTest extends TestCase
{
    public function testKeepsTheHighestCounts(): void
    {
        $heap = new TopKHeap(3);

        foreach (['a', 'b', 'a', 'c', 'a', 'b', 'd', 'd', 'd', 'd'] as $key) {
            $heap->observe($key);
        }

        self::assertSame([
            ['key' => 'd', 'count' => 4],
            ['key' => 'a', 'count' => 3],
            ['key' => 'b', 'count' => 2],
        ], $heap->top());
        self::assertSame(4, $heap->distinctCount());
        self::assertSame(3, $heap->size());
        self::assertSame(3, $heap->countFor('a'));
        self::assertSame(1, $heap->countFor('c'));
        self::assertSame(0, $heap->countFor('missing'));
    }

    public function testTiesAreBrokenByKey(): void
    {
        $heap = new TopKHeap(2);
        $heap->observe('b');
        $heap->observe('a');
        $heap->observe('c');

        self::assertSame([
            ['key' => 'a', 'count' => 1],
            ['key' => 'b', 'count' => 1],
        ], $heap->top());
    }

    public function testExistingKeyCanReenterTheHeap(): void
    {
        $heap = new TopKHeap(2);
        $heap->observe('a');
        $heap->observe('b');
        $heap->observe('c');
        $heap->observe('a', 5);

        self::assertSame([
            ['key' => 'a', 'count' => 6],
            ['key' => 'b', 'count' => 1],
        ], $heap->top());
    }

    public function testMatchesAFullSortOnRandomizedStreams(): void
    {
        foreach ([1, 3, 10, 50] as $k) {
            $random = new DeterministicRandom('topk-' . $k);
            $heap = new TopKHeap($k);
            $counts = [];

            for ($index = 0; $index < 1_000; ++$index) {
                $key = 'key-' . $random->intBetween(0, 100);
                $heap->observe($key);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }

            $entries = [];

            foreach ($counts as $key => $count) {
                $entries[] = ['key' => $key, 'count' => $count];
            }

            usort($entries, static fn (array $left, array $right): int => ($right['count'] <=> $left['count']) ?: strcmp($left['key'], $right['key']));

            self::assertSame(array_slice($entries, 0, min($k, count($entries))), $heap->top());
        }
    }

    public function testCountersRecordHeapComparisons(): void
    {
        $counters = new Counters();
        $heap = new TopKHeap(2, $counters);

        foreach (['a', 'b', 'c', 'a', 'b'] as $key) {
            $heap->observe($key);
        }

        self::assertGreaterThan(0, $counters->comparisons);
    }

    public function testRejectsInvalidConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TopKHeap(0);
    }

    public function testRejectsNonPositiveIncrement(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TopKHeap(2)->observe('a', 0);
    }
}
