<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Instrumentation;

use AlgorithmsLab\Instrumentation\Stopwatch;
use LogicException;
use PHPUnit\Framework\TestCase;

final class StopwatchTest extends TestCase
{
    public function testMeasuresElapsedTime(): void
    {
        $watch = new Stopwatch();
        $watch->start();

        self::assertTrue($watch->isRunning());
        self::assertGreaterThanOrEqual(0.0, $watch->elapsedSeconds());

        $elapsed = $watch->stop();

        self::assertGreaterThanOrEqual(0.0, $elapsed);
        self::assertFalse($watch->isRunning());
        self::assertSame($elapsed, $watch->elapsedSeconds());
    }

    public function testElapsedBeforeStartIsZero(): void
    {
        self::assertSame(0.0, new Stopwatch()->elapsedSeconds());
    }

    public function testDoubleStartIsRejected(): void
    {
        $watch = new Stopwatch();
        $watch->start();

        $this->expectException(LogicException::class);

        $watch->start();
    }

    public function testStopWithoutStartIsRejected(): void
    {
        $this->expectException(LogicException::class);

        new Stopwatch()->stop();
    }
}
