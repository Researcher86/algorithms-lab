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
    name: 'sort:fan-in',
    description: 'External merge fan-in and the cost of extra passes.',
    supports: ['input-mb', 'chunk-mb', 'seed'],
    run: static function (Options $options, Output $output): void {
        $root = dirname(__DIR__, 2);
        $inputMb = max(1, $options->inputMb(100));
        $chunkMb = max(1, $options->chunkMb(8));
        $seed = $options->seed('book');
        $dataset = DatasetCache::ensure($root . '/var/datasets', 'random', $inputMb * 1024 * 1024, $seed);
        $chunkBytes = $chunkMb * 1024 * 1024;

        $output->heading(
            'External sort — fan-in',
            'A larger fan-in opens more runs at once but usually removes a merge pass.',
        );
        $output->line(sprintf('%-8s %8s %8s %10s %12s %12s %12s', 'fan-in', 'runs', 'passes', 'open', 'seconds', 'MiB/s', 'peak MiB'));
        $output->line(str_repeat('-', 84));

        foreach ([2, 8, 32, 128] as $fanIn) {
            $outputPath = $root . '/var/tmp/sort-fan-in-' . $fanIn . '.txt';
            @unlink($outputPath);
            $sorter = new ExternalSorter($root . '/var/tmp', $chunkBytes, $fanIn);
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
            $openFiles = min($fanIn, max(1, $stats->runs));

            $output->line(sprintf(
                '%-8d %8d %8d %10d %9.3f s %9.2f %9.1f',
                $fanIn,
                $stats->runs,
                $stats->passes,
                $openFiles,
                $seconds,
                $throughput,
                $peak / 1024 / 1024,
            ));
            $output->measure('fan_in_' . $fanIn . '_runs', $stats->runs);
            $output->measure('fan_in_' . $fanIn . '_passes', $stats->passes);
            $output->measure('fan_in_' . $fanIn . '_open_files', $openFiles);
            $output->measure('fan_in_' . $fanIn . '_seconds', $seconds);
            $output->measure('fan_in_' . $fanIn . '_io_bytes', $ioBytes);
            $output->measure('fan_in_' . $fanIn . '_mb_per_second', $throughput);
            $output->measure('fan_in_' . $fanIn . '_peak_bytes', $peak);
            @unlink($outputPath);
        }

        $output->line();
        $output->line('Passes include the final merge; extra passes rewrite intermediate runs and increase logical I/O.');
        $output->line('The open-file count is the smaller of fan-in and the initial run count.');
        $output->line(sprintf(
            'Input: %s records, %.1f MiB. Chunk: %d MiB. Seed: %s.',
            number_format($dataset['records']),
            $dataset['bytes'] / 1024 / 1024,
            $chunkMb,
            $seed,
        ));
        $output->measure('input_records', $dataset['records']);
        $output->measure('input_bytes', $dataset['bytes']);
        $output->measure('input_mb', $inputMb);
        $output->measure('chunk_mb', $chunkMb);
        $output->measure('seed', $seed);
    },
);
