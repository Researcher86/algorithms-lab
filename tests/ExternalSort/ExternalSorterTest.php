<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\ExternalSort;

use AlgorithmsLab\ExternalSort\ExternalSorter;
use AlgorithmsLab\ExternalSort\LineReader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExternalSorterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/algorithms-lab-sort-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        @rmdir($this->root);
    }

    public function testSortsAStreamingGeneratorAcrossSeveralRuns(): void
    {
        $records = ['delta', 'alpha', 'charlie', 'bravo', 'echo', 'foxtrot', 'golf', 'hotel'];
        $sorter = new ExternalSorter($this->root, 10, 2);
        $stats = $sorter->sort($records, $this->root . '/out.txt');
        $output = iterator_to_array(LineReader::lines($this->root . '/out.txt'), false);

        self::assertSame(['alpha', 'bravo', 'charlie', 'delta', 'echo', 'foxtrot', 'golf', 'hotel'], $output);
        self::assertSame(8, $stats->records);
        self::assertGreaterThan(1, $stats->runs);
        self::assertGreaterThan(0, $stats->passes);
        self::assertGreaterThan(0, $stats->comparisons);
        self::assertGreaterThan(0, $stats->bytesRead);
        self::assertGreaterThan(0, $stats->bytesWritten);
        self::assertSame([], glob($this->root . '/run-*') ?: []);
    }

    public function testSortFileReadsAndWritesNewlineRecords(): void
    {
        $input = $this->root . '/input.txt';
        $output = $this->root . '/out.txt';
        file_put_contents($input, "charlie\nalpha\nbravo\n");

        $stats = new ExternalSorter($this->root, 5, 2)->sortFile($input, $output);

        self::assertSame(['alpha', 'bravo', 'charlie'], iterator_to_array(LineReader::lines($output), false));
        self::assertSame(3, $stats->records);
        self::assertSame(20, filesize($output));
    }

    public function testEqualKeysKeepInputOrderAcrossRuns(): void
    {
        $records = ['1 alpha', '1 bravo', '1 charlie', '0 x', '0 y', '2 z'];
        $comparator = static fn (string $left, string $right): int => strcmp(substr($left, 0, 1), substr($right, 0, 1));
        $sorter = new ExternalSorter($this->root, 4, 2, $comparator);
        $sorter->sort($records, $this->root . '/out.txt');

        self::assertSame([
            '0 x',
            '0 y',
            '1 alpha',
            '1 bravo',
            '1 charlie',
            '2 z',
        ], iterator_to_array(LineReader::lines($this->root . '/out.txt'), false));
    }

    public function testEmptyInputCreatesAnEmptyOutput(): void
    {
        $stats = new ExternalSorter($this->root, 10, 2)->sort([], $this->root . '/out.txt');

        self::assertSame(0, $stats->records);
        self::assertSame(0, $stats->runs);
        self::assertSame(0, $stats->passes);
        self::assertSame(0, filesize($this->root . '/out.txt'));
    }

    public function testRejectsInvalidChunkSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExternalSorter($this->root, 0);
    }

    public function testRejectsFanInBelowTwo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExternalSorter($this->root, 10, 1);
    }

    public function testRejectsAMissingInputFile(): void
    {
        $this->expectException(RuntimeException::class);

        new ExternalSorter($this->root)->sortFile($this->root . '/missing.txt', $this->root . '/out.txt');
    }
}
