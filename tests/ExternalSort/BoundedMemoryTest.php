<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\ExternalSort;

use AlgorithmsLab\ExternalSort\DatasetGenerator;
use PHPUnit\Framework\TestCase;

/**
 * The normal container limit is 512M. These tests lower it in a child process:
 * an 8 MiB input is small on disk but too large for a naive in-memory sort
 * under 8M, while the external sorter's chunk stays bounded.
 */
final class BoundedMemoryTest extends TestCase
{
    private string $directory;
    private string $input;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/algorithms-lab-external-memory-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o777, true);
        $this->input = $this->directory . '/input.txt';
        $info = DatasetGenerator::writeFile($this->input, 'random', DatasetGenerator::recordsForBytes(8 * 1024 * 1024), 'memory-test');

        self::assertSame($info['bytes'], filesize($this->input));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        @rmdir($this->directory);
    }

    public function testExternalSortFitsALoweredLimit(): void
    {
        $output = $this->directory . '/output.txt';
        [$exit, $result] = $this->runChild('external', $output);

        self::assertSame(0, $exit, $result);
        self::assertStringContainsString('ok external', $result);
        self::assertSame(filesize($this->input), filesize($output));
    }

    public function testNaiveInMemorySortFailsAtTheSameLimit(): void
    {
        [$exit, $result] = $this->runChild('in-memory', $this->directory . '/unused.txt');

        self::assertNotSame(0, $exit, 'The 8 MiB input should not fit in 8M when read with file()');
        self::assertMatchesRegularExpression('/Allowed memory size|memory exhausted/i', $result);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runChild(string $mode, string $output): array
    {
        $script = $this->writeScript();
        $command = [PHP_BINARY, '-d', 'memory_limit=8M', $script, $mode, $this->input, $output];
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
            $input = $argv[2] ?? '';
            $output = $argv[3] ?? '';

            if ($mode === 'in-memory') {
                $records = file($input, FILE_IGNORE_NEW_LINES);

                if ($records === false) {
                    throw new RuntimeException('Could not read the input');
                }

                usort($records, 'strcmp');
                fwrite(STDOUT, 'ok in-memory ' . count($records) . PHP_EOL);
                exit(0);
            }

            if ($mode === 'external') {
                $sorter = new \AlgorithmsLab\ExternalSort\ExternalSorter(dirname($output), 512 * 1024, 4);
                $stats = $sorter->sortFile($input, $output);
                fwrite(STDOUT, 'ok external ' . $stats->records . ' ' . $stats->runs . ' ' . $stats->passes . PHP_EOL);
                exit(0);
            }

            throw new InvalidArgumentException('unknown mode: ' . $mode);
            PHP;

        $script = tempnam(sys_get_temp_dir(), 'algorithms-lab-external-memory-script-');

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
