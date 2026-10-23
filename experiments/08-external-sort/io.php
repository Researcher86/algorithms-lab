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
    name: 'sort:io',
    description: 'External sort I/O, duration and peak memory for one input size.',
    supports: ['input-mb', 'chunk-mb', 'fan-in', 'seed'],
    run: static function (Options $options, Output $output): void {
        $root = dirname(__DIR__, 2);
        $inputMb = max(1, $options->inputMb(100));
        $chunkMb = max(1, $options->chunkMb(8));
        $fanIn = max(2, $options->fanIn(32));
        $seed = $options->seed('book');
        $dataset = DatasetCache::ensure($root . '/var/datasets', 'random', $inputMb * 1024 * 1024, $seed);
        $outputPath = $root . '/var/tmp/sort-io.txt';
        @unlink($outputPath);
        $sorter = new ExternalSorter($root . '/var/tmp', $chunkMb * 1024 * 1024, $fanIn);
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

        $output->heading(
            'External sort — I/O',
            'The sort reads the input, writes and reads spilled runs and writes the result; all of that is counted.',
        );
        $output->line(sprintf('%-16s %s', 'input', sprintf('%s records, %.1f MiB', number_format($dataset['records']), $dataset['bytes'] / 1024 / 1024)));
        $output->line(sprintf('%-16s %s', 'configuration', sprintf('chunk %d MiB, fan-in %d', $chunkMb, $fanIn)));
        $output->line(sprintf('%-16s %d', 'sorted runs', $stats->runs));
        $output->line(sprintf('%-16s %d', 'merge passes', $stats->passes));
        $output->line(sprintf('%-16s %.3f s', 'elapsed', $seconds));
        $output->line(sprintf('%-16s %.1f MiB', 'bytes read', $stats->bytesRead / 1024 / 1024));
        $output->line(sprintf('%-16s %.1f MiB', 'bytes written', $stats->bytesWritten / 1024 / 1024));
        $output->line(sprintf('%-16s %.1f MiB', 'logical I/O', $ioBytes / 1024 / 1024));
        $output->line(sprintf('%-16s %.2f MiB/s', 'throughput', $throughput));
        $output->line(sprintf('%-16s %.1f MiB', 'PHP peak', $peak / 1024 / 1024));
        $output->line(sprintf('%-16s %s', 'memory_limit', (string) ini_get('memory_limit')));
        $output->line();
        $output->line('This is one file, one cache state and one Docker run; it is not a native-disk benchmark.');
        $output->line('The peak is the PHP allocator, not RSS; the OS page cache can serve some reads from memory.');

        $output->measure('input_records', $dataset['records']);
        $output->measure('input_bytes', $dataset['bytes']);
        $output->measure('input_mb', $inputMb);
        $output->measure('chunk_mb', $chunkMb);
        $output->measure('fan_in', $fanIn);
        $output->measure('runs', $stats->runs);
        $output->measure('passes', $stats->passes);
        $output->measure('seconds', $seconds);
        $output->measure('bytes_read', $stats->bytesRead);
        $output->measure('bytes_written', $stats->bytesWritten);
        $output->measure('io_bytes', $ioBytes);
        $output->measure('mb_per_second', $throughput);
        $output->measure('peak_bytes', $peak);
        $output->measure('seed', $seed);
        @unlink($outputPath);
    },
);
