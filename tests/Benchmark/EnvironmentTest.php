<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Benchmark;

use AlgorithmsLab\Benchmark\Environment;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    public function testDetectsTheEnvironment(): void
    {
        $environment = Environment::detect();
        $array = $environment->toArray();

        self::assertSame(PHP_VERSION, $environment->phpVersion);
        self::assertSame(PHP_OS_FAMILY, $environment->os);
        self::assertNotSame('', $environment->architecture);
        self::assertNotSame('', $environment->cpu);
        self::assertGreaterThanOrEqual(1, $environment->cpuCount);
        self::assertNotSame('', $environment->memoryLimit);
        self::assertNotSame('', $environment->xdebugMode);
        self::assertNotSame('', $environment->allocator);
        self::assertNotSame('', $environment->kernel);
        self::assertNotSame('', $environment->commit);
        self::assertArrayHasKey('cgroup_memory_limit', $array);
        self::assertArrayHasKey('opcache', $array);
        self::assertArrayHasKey('jit', $array);
    }
}
