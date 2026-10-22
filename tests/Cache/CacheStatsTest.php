<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Cache;

use AlgorithmsLab\Cache\CacheStats;
use PHPUnit\Framework\TestCase;

final class CacheStatsTest extends TestCase
{
    public function testCountsHitsMissesAndEvictions(): void
    {
        $stats = new CacheStats();
        $stats->recordHit();
        $stats->recordHit();
        $stats->recordMiss();
        $stats->recordEviction();

        self::assertSame(2, $stats->hits);
        self::assertSame(1, $stats->misses);
        self::assertSame(3, $stats->requests());
        self::assertEqualsWithDelta(2 / 3, $stats->hitRate(), 1e-10);
        self::assertSame(
            ['hits' => 2, 'misses' => 1, 'evictions' => 1, 'requests' => 3],
            $stats->toArray(),
        );
    }

    public function testEmptyStatsHaveAZeroHitRate(): void
    {
        self::assertSame(0.0, new CacheStats()->hitRate());
    }

    public function testResetClearsEveryCount(): void
    {
        $stats = new CacheStats();
        $stats->recordHit();
        $stats->recordMiss();
        $stats->recordEviction();
        $stats->reset();

        self::assertSame(['hits' => 0, 'misses' => 0, 'evictions' => 0, 'requests' => 0], $stats->toArray());
    }
}
