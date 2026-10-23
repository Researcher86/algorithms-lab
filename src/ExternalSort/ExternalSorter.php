<?php

declare(strict_types=1);

namespace AlgorithmsLab\ExternalSort;

use AlgorithmsLab\Foundation\BinaryHeap;
use AlgorithmsLab\Foundation\Comparator;
use Closure;
use Generator;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Chunk-sort-spill external merge sort.
 *
 * The sorter never holds the whole input: ChunkReader caps each in-memory
 * chunk, each sorted chunk becomes a temporary run, and a k-way heap merges at
 * most `fanIn` runs at a time. Multi-pass merges reduce the run count in
 * fan-in-sized groups until one final merge reaches the output.
 *
 * The comparator works on whole records. Stability comes from two rules: PHP's
 * stable usort preserves input order inside a chunk, and the merge heap breaks
 * comparator ties by run number. A multi-pass merge preserves the order of its
 * groups, so equal records still emerge in input order.
 */
final class ExternalSorter
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var Closure(string, string): int */
    private readonly Closure $compare;

    private readonly Comparator $order;

    /**
     * @param Closure(string, string): int|null $comparator
     */
    public function __construct(
        private readonly string $directory,
        private readonly int $chunkBytes = 8 * 1024 * 1024,
        private readonly int $fanIn = 8,
        ?Closure $comparator = null,
    ) {
        if ($chunkBytes < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1 byte');
        }

        if ($fanIn < 2) {
            throw new InvalidArgumentException('Fan-in must be at least 2');
        }

        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create sort directory: %s', $directory));
        }

        $this->compare = $comparator ?? static fn (string $left, string $right): int => strcmp($left, $right);
        $this->order = Comparator::of(function (mixed $left, mixed $right): int {
            if (!$left instanceof MergeEntry || !$right instanceof MergeEntry) {
                throw new LogicException('The merge heap only accepts MergeEntry values');
            }

            $result = ($this->compare)($left->value, $right->value);

            if ($result !== 0) {
                return $result;
            }

            return $left->run <=> $right->run;
        });
    }

    public function sortFile(string $inputPath, string $outputPath): SortStats
    {
        if (!is_file($inputPath) || !is_readable($inputPath)) {
            throw new RuntimeException(sprintf('Input file is not readable: %s', $inputPath));
        }

        return $this->sort(LineReader::lines($inputPath), $outputPath);
    }

    /**
     * @param iterable<string> $records
     */
    public function sort(iterable $records, string $outputPath): SortStats
    {
        $this->order->reset();
        $this->temporaryFiles = [];
        $runs = [];
        $recordCount = 0;
        $bytesRead = 0;
        $bytesWritten = 0;
        $chunks = 0;

        try {
            $reader = new ChunkReader($this->chunkBytes);

            foreach ($reader->chunks($records) as $chunk) {
                ++$chunks;
                $recordCount += count($chunk);

                foreach ($chunk as $record) {
                    $bytesRead += strlen($record) + 1;
                }

                usort($chunk, $this->compare);
                $runs[] = $this->writeRun($chunk, $bytesWritten);
            }

            if ($runs === []) {
                $this->writeEmpty($outputPath);

                return $this->stats($recordCount, 0, 0, $bytesRead, $bytesWritten);
            }

            $passes = 0;
            $fanIn = $this->fanIn;

            if ($fanIn < 2) {
                throw new LogicException('Fan-in must be at least 2');
            }

            while (count($runs) > $fanIn) {
                $next = [];

                foreach (array_chunk($runs, $fanIn) as $group) {
                    $next[] = $this->mergeToTemporary($group, $bytesRead, $bytesWritten);
                }

                foreach ($runs as $run) {
                    if (is_file($run)) {
                        @unlink($run);
                    }

                    $this->forget($run);
                }

                $runs = $next;
                ++$passes;
            }

            $this->merge($runs, $outputPath, $bytesRead, $bytesWritten);
            ++$passes;

            return $this->stats($recordCount, $chunks, $passes, $bytesRead, $bytesWritten);
        } finally {
            $this->cleanup();
        }
    }

    public function chunkBytes(): int
    {
        return $this->chunkBytes;
    }

    public function fanIn(): int
    {
        return $this->fanIn;
    }

    /**
     * @param list<string> $records
     */
    private function writeRun(array $records, int &$bytesWritten): string
    {
        $path = $this->newRunPath();
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Cannot create run file: %s', $path));
        }

        $buffer = '';

        try {
            foreach ($records as $record) {
                $line = $record . "\n";
                $buffer .= $line;
                $bytesWritten += strlen($line);

                if (strlen($buffer) >= 1_048_576) {
                    $this->writeAll($handle, $buffer);
                    $buffer = '';
                }
            }

            if ($buffer !== '') {
                $this->writeAll($handle, $buffer);
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }

    /**
     * @param list<string> $runs
     */
    private function mergeToTemporary(array $runs, int &$bytesRead, int &$bytesWritten): string
    {
        $path = $this->newRunPath();
        $this->merge($runs, $path, $bytesRead, $bytesWritten);

        return $path;
    }

    /**
     * @param list<string> $runs
     */
    private function merge(array $runs, string $outputPath, int &$bytesRead, int &$bytesWritten): void
    {
        $heap = new BinaryHeap($this->order);
        /** @var array<int, Generator<int, string>> $generators */
        $generators = [];

        foreach ($runs as $runIndex => $run) {
            $generator = LineReader::lines($run);
            $generator->rewind();

            if (!$generator->valid()) {
                continue;
            }

            $value = (string) $generator->current();
            $bytesRead += strlen($value) + 1;
            $generators[$runIndex] = $generator;
            $heap->insert(new MergeEntry($value, $runIndex));
        }

        $handle = fopen($outputPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Cannot create output file: %s', $outputPath));
        }

        $buffer = '';

        try {
            while (!$heap->isEmpty()) {
                $entry = $heap->extract();

                if (!$entry instanceof MergeEntry) {
                    throw new LogicException('The merge heap returned a non-MergeEntry value');
                }

                $line = $entry->value . "\n";
                $buffer .= $line;
                $bytesWritten += strlen($line);

                if (strlen($buffer) >= 1_048_576) {
                    $this->writeAll($handle, $buffer);
                    $buffer = '';
                }

                $generator = $generators[$entry->run] ?? null;

                if ($generator === null) {
                    throw new LogicException('The merge entry points at a closed run');
                }

                $generator->next();

                if ($generator->valid()) {
                    $value = (string) $generator->current();
                    $bytesRead += strlen($value) + 1;
                    $heap->insert(new MergeEntry($value, $entry->run));
                } else {
                    unset($generators[$entry->run]);
                }
            }

            if ($buffer !== '') {
                $this->writeAll($handle, $buffer);
            }
        } finally {
            fclose($handle);
        }
    }

    private function writeEmpty(string $outputPath): void
    {
        $handle = fopen($outputPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Cannot create output file: %s', $outputPath));
        }

        fclose($handle);
    }

    /**
     * @param resource $handle
     */
    private function writeAll($handle, string $line): void
    {
        $length = strlen($line);
        $offset = 0;

        while ($offset < $length) {
            $written = fwrite($handle, substr($line, $offset));

            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write a sorted record');
            }

            $offset += $written;
        }
    }

    private function newRunPath(): string
    {
        $path = tempnam($this->directory, 'run-');

        if ($path === false) {
            throw new RuntimeException(sprintf('Cannot create a temporary run in %s', $this->directory));
        }

        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function forget(string $path): void
    {
        $index = array_search($path, $this->temporaryFiles, true);

        if ($index !== false) {
            array_splice($this->temporaryFiles, $index, 1);
        }
    }

    private function cleanup(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->temporaryFiles = [];
    }

    private function stats(int $records, int $runs, int $passes, int $bytesRead, int $bytesWritten): SortStats
    {
        return new SortStats(
            records: $records,
            runs: $runs,
            passes: $passes,
            bytesRead: $bytesRead,
            bytesWritten: $bytesWritten,
            comparisons: $this->order->comparisons(),
            chunkBytes: $this->chunkBytes,
            fanIn: $this->fanIn,
        );
    }
}
