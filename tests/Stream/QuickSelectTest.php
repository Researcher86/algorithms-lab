<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Stream;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Stream\QuickSelect;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QuickSelectTest extends TestCase
{
    public function testSelectsTheTopCounts(): void
    {
        $top = QuickSelect::top(['a' => 3, 'b' => 9, 'c' => 1, 'd' => 5], 2);

        self::assertSame([
            ['key' => 'b', 'count' => 9],
            ['key' => 'd', 'count' => 5],
        ], $top);
    }

    public function testTiesAreBrokenByKey(): void
    {
        self::assertSame([
            ['key' => 'a', 'count' => 2],
            ['key' => 'b', 'count' => 2],
        ], QuickSelect::top(['c' => 2, 'b' => 2, 'a' => 2], 2));
    }

    public function testKAboveTheUniverseReturnsEverything(): void
    {
        self::assertSame([
            ['key' => 'a', 'count' => 2],
            ['key' => 'b', 'count' => 1],
        ], QuickSelect::top(['b' => 1, 'a' => 2], 10));
    }

    public function testEmptyUniverseAndCounters(): void
    {
        $counters = new Counters();

        self::assertSame([], QuickSelect::top([], 3, $counters));
        QuickSelect::top(['a' => 4, 'b' => 3, 'c' => 2, 'd' => 1], 2, $counters);
        self::assertGreaterThan(0, $counters->comparisons);
    }

    public function testMatchesAFullSortOnRandomizedCounts(): void
    {
        foreach ([1, 2, 10, 100] as $k) {
            $random = new DeterministicRandom('quickselect-' . $k);
            $counts = [];

            for ($index = 0; $index < 500; ++$index) {
                $key = 'key-' . $random->intBetween(0, 200);
                $counts[$key] = ($counts[$key] ?? 0) + $random->intBetween(1, 5);
            }

            $entries = [];

            foreach ($counts as $key => $count) {
                $entries[] = ['key' => $key, 'count' => $count];
            }

            usort($entries, static fn (array $left, array $right): int => ($right['count'] <=> $left['count']) ?: strcmp($left['key'], $right['key']));

            self::assertSame(array_slice($entries, 0, min($k, count($entries))), QuickSelect::top($counts, $k));
        }
    }

    public function testRejectsZeroK(): void
    {
        $this->expectException(InvalidArgumentException::class);

        QuickSelect::top(['a' => 1], 0);
    }
}
