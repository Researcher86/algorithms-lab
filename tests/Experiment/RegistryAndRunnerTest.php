<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Experiment;

use AlgorithmsLab\Experiment\ExperimentRunner;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Experiment\Registry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RegistryAndRunnerTest extends TestCase
{
    public function testDiscoversTheDemoExperiment(): void
    {
        $registry = new Registry(dirname(__DIR__, 2) . '/experiments');

        self::assertTrue($registry->has('demo:smoke'));
        self::assertSame('demo:smoke', $registry->get('demo:smoke')->name);
        self::assertSame(['demo:smoke'], array_keys($registry->all()));
    }

    public function testUnknownExperimentIsRejected(): void
    {
        $this->expectException(RuntimeException::class);

        new Registry(dirname(__DIR__, 2) . '/experiments')->get('missing');
    }

    public function testRunnerReturnsMeasurements(): void
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open a temporary stream');
        }

        $registry = new Registry(dirname(__DIR__, 2) . '/experiments');
        $result = new ExperimentRunner($registry)->run(
            'demo:smoke',
            Options::fromStrings(['elements' => '10', 'iterations' => '1', 'seed' => 'test']),
            new Output($stream, quiet: true),
        );

        self::assertSame('demo:smoke', $result->name);
        self::assertSame(10, $result->measurements['elements']);
        self::assertSame('test', $result->measurements['seed']);
        self::assertIsInt($result->measurements['checksum']);
        self::assertGreaterThanOrEqual(0.0, $result->elapsedSeconds);
    }

    public function testRunnerRefusesAnOptionTheExperimentDoesNotUse(): void
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open a temporary stream');
        }

        $registry = new Registry(dirname(__DIR__, 2) . '/experiments');

        $this->expectException(InvalidArgumentException::class);

        new ExperimentRunner($registry)->run(
            'demo:smoke',
            Options::fromStrings(['capacity' => '10']),
            new Output($stream, quiet: true),
        );
    }
}
