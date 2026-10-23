<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\ExternalSort\DatasetCache;
use AlgorithmsLab\ExternalSort\ExternalSorter;
use AlgorithmsLab\Instrumentation\MemoryMeter;
use AlgorithmsLab\Instrumentation\Stopwatch;

return new Experiment(
    name: 'sort:memory-limit',
    description: 'External sort with 8, 32 and 128 MiB chunk limits.',
    supports: ['input-mb', 'seed'],
    run: static function (Options $options, Output $output): void {
        $root = dirname(__DIR__, 2);
        $inputMb = max(1, $options->inputMb(100));
        $seed = $options->seed('book');
        $dataset = DatasetCache::ensure($root . '/var/datasets', 'random', $inputMb * 1024 * 1024, $seed);

        $output->heading(
            'External sort — chunk size',
            'The chunk is the only part of the input held in memory; the rest is spilled as sorted runs.',
        );
        $output->line(sprintf('%-10s %8s %8s %12s %12s %12s %12s', 'chunk', 'runs', 'passes', 'seconds', 'I/O MiB', 'MiB/s', 'peak MiB'));
        $output->line(str_repeat('-', 83));

        foreach ([8, 32, 128] as $chunkMb) {
            $chunkBytes = $chunkMb * 1024 * 1024;
            $outputPath = $root . '/var/tmp/sort-memory-' . $chunkMb . '.txt';
            @unlink($outputPath);
            $sorter = new ExternalSorter($root . '/var/tmp', $chunkBytes, 8);
            $memory = new MemoryMeter();
            $memory->resetPeak();
            $before = $memory->usage();
            $stopwatch = new Stopwatch();
            $stopwatch->start();
            $stats = $sorter->sortFile($dataset['path'], $outputPath);
            $seconds = $stopwatch->stop();
            $peak = max(0, $memory->peak() - $before);
            $ioBytes = $stats->bytesRead + $stats->bytesWritten;
            $throughput = $seconds > 0.0 ? $ioBytes / 1024 / 1024 / $seconds : 0.0;

            $output->line(sprintf(
                '%-10s %8d %8d %9.3f s %9.1f %9.2f %9.1f',
                $chunkMb . ' MiB',
                $stats->runs,
                $stats->passes,
                $seconds,
                $ioBytes / 1024 / 1024,
                $throughput,
                $peak / 1024 / 1024,
            ));
            $output->measure('chunk_' . $chunkMb . '_runs', $stats->runs);
            $output->measure('chunk_' . $chunkMb . '_passes', $stats->passes);
            $output->measure('chunk_' . $chunkMb . '_seconds', $seconds);
            $output->measure('chunk_' . $chunkMb . '_io_bytes', $ioBytes);
            $output->measure('chunk_' . $chunkMb . '_mb_per_second', $throughput);
            $output->measure('chunk_' . $chunkMb . '_peak_bytes', $peak);
            @unlink($outputPath);
        }

        $output->line();
        $output->line('A larger chunk reduces the number of runs and merge passes, but raises the live PHP chunk footprint.');
        $output->line('I/O MiB is input plus spills plus output; MiB/s is that logical total divided by elapsed time.');
        $output->line(sprintf(
            'Input: %s records, %.1f MiB. PHP memory_limit: %s. Seed: %s.',
            number_format($dataset['records']),
            $dataset['bytes'] / 1024 / 1024,
            (string) ini_get('memory_limit'),
            $seed,
        ));
        $output->measure('input_records', $dataset['records']);
        $output->measure('input_bytes', $dataset['bytes']);
        $output->measure('input_mb', $inputMb);
        $output->measure('seed', $seed);
    },
);
