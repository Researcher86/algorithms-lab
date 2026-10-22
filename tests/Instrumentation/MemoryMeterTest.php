<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Instrumentation;

use AlgorithmsLab\Instrumentation\MemoryMeter;
use PHPUnit\Framework\TestCase;

final class MemoryMeterTest extends TestCase
{
    public function testSnapshotHasTheExpectedShape(): void
    {
        $meter = new MemoryMeter();
        $snapshot = $meter->snapshot();

        self::assertArrayHasKey('usage', $snapshot);
        self::assertArrayHasKey('real_usage', $snapshot);
        self::assertArrayHasKey('peak', $snapshot);
        self::assertArrayHasKey('peak_real', $snapshot);
        self::assertArrayHasKey('delta', $snapshot);

        foreach ($snapshot as $value) {
            self::assertGreaterThanOrEqual(0, $value);
        }
    }

    public function testDeltaIsUsageMinusTheBaseline(): void
    {
        $meter = new MemoryMeter();

        self::assertSame($meter->usage() - $meter->baseline(), $meter->delta());
    }

    public function testPeakCanBeReset(): void
    {
        $meter = new MemoryMeter();
        $meter->resetPeak();

        self::assertGreaterThanOrEqual(0, $meter->peak());
    }
}
