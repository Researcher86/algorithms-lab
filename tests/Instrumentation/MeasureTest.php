<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Instrumentation;

use AlgorithmsLab\Instrumentation\Measure;
use LogicException;
use PHPUnit\Framework\TestCase;

final class MeasureTest extends TestCase
{
    public function testCollectsCountersTimeAndMemory(): void
    {
        $measure = new Measure();
        $measure->start();
        $measure->counters->comparisons = 2;
        $data = range(1, 100);
        $measure->stop();

        $result = $measure->toArray();

        self::assertSame(2, $result['comparisons']);
        self::assertArrayHasKey('elapsed_seconds', $result);
        self::assertArrayHasKey('php_delta_bytes', $result);
        self::assertGreaterThanOrEqual(0.0, $result['elapsed_seconds']);
    }

    public function testResultCannotBeReadWhileRunning(): void
    {
        $measure = new Measure();
        $measure->start();

        $this->expectException(LogicException::class);

        $measure->toArray();
    }
}
