<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\ExternalSort\DatasetCache;
use AlgorithmsLab\ExternalSort\DatasetGenerator;
use AlgorithmsLab\ExternalSort\ExternalSorter;
use AlgorithmsLab\ExternalSort\LineReader;
use AlgorithmsLab\Instrumentation\MemoryMeter;
use AlgorithmsLab\Instrumentation\Stopwatch;

return new Experiment(
    name: 'sort:patterns',
    description: 'Random, sorted, reversed and duplicate-heavy records, checked against an in-memory sort.',
    supports: ['elements', 'seed'],
    run: static function (Options $options, Output $output): void {
        $root = dirname(__DIR__, 2);
        $records = max(1, $options->elements(10_000));
        $seed = $options->seed('book');
        $targetBytes = $records * DatasetGenerator::recordBytes();
        $comparator = DatasetGenerator::keyComparator();

        $output->heading(
            'External sort — input patterns',
            'Every output is compared byte-for-byte with PHP usort on a reduced set; the comparator sees only the key.',
        );
        $output->line(sprintf('%-12s %8s %8s %8s %12s %10s %12s', 'pattern', 'records', 'runs', 'passes', 'seconds', 'checksum', 'peak MiB'));
        $output->line(str_repeat('-', 79));

        foreach (DatasetGenerator::patterns() as $pattern) {
            $dataset = DatasetCache::ensure($root . '/var/datasets', $pattern, $targetBytes, $seed);
            $expectedRecords = iterator_to_array(DatasetGenerator::records($pattern, $dataset['records'], $seed), false);
            $memorySort = new Stopwatch();
            $memorySort->start();
            usort($expectedRecords, $comparator);
            $memorySort->stop();
            $expectedHash = hash('xxh128', implode("\n", $expectedRecords) . "\n");
            $outputPath = $root . '/var/tmp/sort-pattern-' . $pattern . '.txt';
            @unlink($outputPath);

            $memory = new MemoryMeter();
            $memory->resetPeak();
            $before = $memory->usage();
            $sorter = new ExternalSorter($root . '/var/tmp', 16 * 1024, 4, $comparator);
            $stopwatch = new Stopwatch();
            $stopwatch->start();
            $stats = $sorter->sortFile($dataset['path'], $outputPath);
            $seconds = $stopwatch->stop();
            $peak = max(0, $memory->peak() - $before);

            $hash = hash_init('xxh128');
            $actualRecords = 0;

            foreach (LineReader::lines($outputPath) as $line) {
                hash_update($hash, $line . "\n");
                ++$actualRecords;
            }

            $checksum = hash_final($hash) === $expectedHash && $actualRecords === $dataset['records'];

            if (!$checksum) {
                throw new LogicException(sprintf('External sort output did not match in-memory sort for %s', $pattern));
            }

            $output->line(sprintf(
                '%-12s %8d %8d %8d %9.3f s %10s %9.1f',
                $pattern,
                $dataset['records'],
                $stats->runs,
                $stats->passes,
                $seconds,
                'ok',
                $peak / 1024 / 1024,
            ));
            $output->measure($pattern . '_records', $dataset['records']);
            $output->measure($pattern . '_runs', $stats->runs);
            $output->measure($pattern . '_passes', $stats->passes);
            $output->measure($pattern . '_seconds', $seconds);
            $output->measure($pattern . '_in_memory_seconds', $memorySort->elapsedSeconds());
            $output->measure($pattern . '_checksum', $checksum);
            $output->measure($pattern . '_peak_bytes', $peak);
            @unlink($outputPath);
        }

        $output->line();
        $output->line('The duplicate pattern has many equal keys: stable chunks plus run-number tie-breaking preserve input order.');
        $output->line('The checksum is over the complete output, not a sample. The in-memory sort is only the small reference.');
        $output->line(sprintf('Records per pattern: %s. Chunk: 16 KiB. Fan-in: 4. Seed: %s.', number_format($records), $seed));
        $output->measure('records', $records);
        $output->measure('seed', $seed);
    },
);
