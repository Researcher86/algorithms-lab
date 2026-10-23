<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Stream;

use PHPUnit\Framework\TestCase;

/**
 * The container runs with a generous limit. These tests lower it in a child
 * process so a failure cannot poison the PHPUnit process itself.
 */
final class BoundedMemoryTest extends TestCase
{
    private const MEMORY_LIMIT = '8M';
    private const ITEMS = 150_000;

    public function testExactFrequencyMapExhaustsTheLoweredLimit(): void
    {
        [$exit, $output] = $this->runChild('exact');

        self::assertNotSame(0, $exit, 'The exact map should not fit in 8M');
        self::assertMatchesRegularExpression('/Allowed memory size|memory exhausted/i', $output);
    }

    public function testCountMinSketchFitsTheLoweredLimit(): void
    {
        [$exit, $output] = $this->runChild('sketch');

        self::assertSame(0, $exit, $output);
        self::assertStringContainsString('ok sketch', $output);
    }

    public function testSpaceSavingFitsTheLoweredLimit(): void
    {
        [$exit, $output] = $this->runChild('space-saving');

        self::assertSame(0, $exit, $output);
        self::assertStringContainsString('ok space-saving', $output);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runChild(string $mode): array
    {
        $script = $this->writeScript();
        $command = [PHP_BINARY, '-d', 'memory_limit=' . self::MEMORY_LIMIT, $script, $mode, (string) self::ITEMS];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2));

        if (!is_resource($process)) {
            self::fail('Could not start the low-memory child process');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        unlink($script);

        return [$exit, ($stdout === false ? '' : $stdout) . ($stderr === false ? '' : $stderr)];
    }

    private function writeScript(): string
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $template = <<<'PHP'
            <?php

            declare(strict_types=1);

            require %s;

            $mode = $argv[1] ?? '';
            $items = (int) ($argv[2] ?? 0);
            $summary = match ($mode) {
                'exact' => new \AlgorithmsLab\Stream\TopKHeap(100),
                'sketch' => new \AlgorithmsLab\Stream\CountMinSketch(1024, 4),
                'space-saving' => new \AlgorithmsLab\Stream\SpaceSaving(100),
                default => throw new \InvalidArgumentException('unknown mode: ' . $mode),
            };

            for ($index = 0; $index < $items; ++$index) {
                $key = 'unique-' . $index;

                if ($mode === 'sketch') {
                    $summary->add($key);
                } else {
                    $summary->observe($key);
                }
            }

            $size = $mode === 'sketch' ? $summary->cells() : $summary->size();
            fwrite(STDOUT, 'ok ' . $mode . ' size=' . $size . PHP_EOL);
            PHP;

        $script = tempnam(sys_get_temp_dir(), 'algorithms-lab-memory-');

        if ($script === false) {
            self::fail('Could not create the low-memory script');
        }

        $template = preg_replace('/^[ \t]+/m', '', $template);

        if ($template === null) {
            unlink($script);
            self::fail('Could not normalize the low-memory script');
        }

        $source = sprintf($template, var_export($autoload, true));

        if (file_put_contents($script, $source) === false) {
            unlink($script);
            self::fail('Could not write the low-memory script');
        }

        return $script;
    }
}
