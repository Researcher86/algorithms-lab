<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Benchmark;

use AlgorithmsLab\Benchmark\BenchmarkRunner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BenchmarkRunnerTest extends TestCase
{
    public function testRunsWarmupsAndTimedRepetitions(): void
    {
        $calls = 0;
        $runner = new BenchmarkRunner(repetitions: 3, warmups: 1);
        $result = $runner->run('counter', static function () use (&$calls): void {
            ++$calls;
        }, 2);

        self::assertSame(8, $calls);
        self::assertSame(2, $result->iterations);
        self::assertSame(3, $result->repetitions);
        self::assertGreaterThan(0.0, $result->timings->median);
        self::assertGreaterThan(0.0, $result->operationsPerSecond());
        self::assertGreaterThanOrEqual(0.0, $result->secondsPerOperation());
    }

    public function testZeroWarmupsIsAllowed(): void
    {
        $calls = 0;
        $runner = new BenchmarkRunner(repetitions: 2, warmups: 0);
        $runner->run('counter', static function () use (&$calls): void {
            ++$calls;
        });

        self::assertSame(2, $calls);
    }

    public function testRejectsZeroIterations(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BenchmarkRunner()->run('bad', static function (): void {
        }, 0);
    }

    public function testRejectsZeroRepetitions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BenchmarkRunner(repetitions: 0);
    }

    public function testRejectsNegativeWarmups(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BenchmarkRunner(warmups: -1);
    }
}
