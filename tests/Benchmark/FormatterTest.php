<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Benchmark;

use AlgorithmsLab\Benchmark\BenchmarkReport;
use AlgorithmsLab\Benchmark\BenchmarkResult;
use AlgorithmsLab\Benchmark\Environment;
use AlgorithmsLab\Benchmark\Formatter\CsvFormatter;
use AlgorithmsLab\Benchmark\Formatter\JsonFormatter;
use AlgorithmsLab\Benchmark\Formatter\TextFormatter;
use AlgorithmsLab\Benchmark\Timings;
use PHPUnit\Framework\TestCase;

final class FormatterTest extends TestCase
{
    private function report(): BenchmarkReport
    {
        return new BenchmarkReport(
            'demo',
            Environment::detect(),
            [
                new BenchmarkResult(
                    name: 'example',
                    iterations: 10,
                    repetitions: 3,
                    elapsedSeconds: 0.6,
                    timings: Timings::of([0.1, 0.2, 0.3]),
                    phpDelta: 1024,
                    peakDelta: 2048,
                ),
            ],
        );
    }

    public function testTextContainsTheEnvironmentAndTheResult(): void
    {
        $text = new TextFormatter()->format($this->report());

        self::assertStringContainsString('Benchmark suite: demo', $text);
        self::assertStringContainsString('PHP ' . PHP_VERSION, $text);
        self::assertStringContainsString('Xdebug', $text);
        self::assertStringContainsString('example', $text);
        self::assertStringContainsString('ops/sec', $text);
    }

    public function testJsonIsValidAndCarriesTheEnvironment(): void
    {
        $json = new JsonFormatter()->format($this->report());
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame('demo', $decoded['suite']);
        self::assertArrayHasKey('environment', $decoded);
        self::assertArrayHasKey('results', $decoded);
        self::assertIsArray($decoded['results']);
        self::assertSame('example', $decoded['results'][0]['name']);
        self::assertArrayHasKey('mad', $decoded['results'][0]['seconds']);
    }

    public function testCsvContainsAHeaderAndOneRow(): void
    {
        $csv = new CsvFormatter()->format($this->report());
        $lines = array_values(array_filter(explode("\n", trim($csv)), static fn (string $line): bool => $line !== ''));

        self::assertCount(2, $lines);
        self::assertStringContainsString('suite,benchmark', $lines[0]);
        self::assertStringContainsString('demo,example', $lines[1]);
    }
}
