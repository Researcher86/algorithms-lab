<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Experiment;

use AlgorithmsLab\Experiment\Output;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OutputTest extends TestCase
{
    public function testWritesProseAndCollectsMeasurements(): void
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open a temporary stream');
        }

        $output = new Output($stream);
        $output->heading('Demo', 'A description');
        $output->line('hello');
        $output->printf("value: %d\n", 42);
        $output->measure('elements', 42);
        $output->measure('ratio', 0.5);
        $output->measure('ok', true);

        rewind($stream);
        $written = (string) stream_get_contents($stream);

        self::assertStringContainsString("Experiment: Demo\nA description\n", $written);
        self::assertStringContainsString('hello', $written);
        self::assertStringContainsString('value: 42', $written);
        self::assertSame(['elements' => 42, 'ratio' => 0.5, 'ok' => true], $output->measurements());
    }

    public function testQuietModeSuppressesProseButKeepsMeasurements(): void
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open a temporary stream');
        }

        $output = new Output($stream, quiet: true);
        $output->line('hidden');
        $output->measure('value', 7);

        rewind($stream);

        self::assertSame('', (string) stream_get_contents($stream));
        self::assertSame(['value' => 7], $output->measurements());
    }
}
