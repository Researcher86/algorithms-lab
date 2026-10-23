<?php

declare(strict_types=1);

use AlgorithmsLab\Benchmark\Benchmark;
use AlgorithmsLab\ExternalSort\DatasetCache;
use AlgorithmsLab\ExternalSort\ExternalSorter;

$root = dirname(__DIR__);
$dataDirectory = $root . '/var/datasets';
$tempDirectory = $root . '/var/benchmarks/tmp';
$outputDirectory = $root . '/var/benchmarks';

foreach ([$tempDirectory, $outputDirectory] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
        throw new RuntimeException(sprintf('Cannot create benchmark directory: %s', $directory));
    }
}

$dataset = DatasetCache::ensure($dataDirectory, 'random', 8 * 1024 * 1024, 'benchmark');

return [
    new Benchmark('external-sort-chunk-1m', 1, static function () use ($dataset, $tempDirectory, $outputDirectory): void {
        new ExternalSorter($tempDirectory, 1024 * 1024, 4)->sortFile($dataset['path'], $outputDirectory . '/external-sort-chunk-1m.txt');
    }),
    new Benchmark('external-sort-chunk-4m', 1, static function () use ($dataset, $tempDirectory, $outputDirectory): void {
        new ExternalSorter($tempDirectory, 4 * 1024 * 1024, 4)->sortFile($dataset['path'], $outputDirectory . '/external-sort-chunk-4m.txt');
    }),
    new Benchmark('external-sort-fanin-2', 1, static function () use ($dataset, $tempDirectory, $outputDirectory): void {
        new ExternalSorter($tempDirectory, 1024 * 1024, 2)->sortFile($dataset['path'], $outputDirectory . '/external-sort-fanin-2.txt');
    }),
    new Benchmark('external-sort-fanin-16', 1, static function () use ($dataset, $tempDirectory, $outputDirectory): void {
        new ExternalSorter($tempDirectory, 1024 * 1024, 16)->sortFile($dataset['path'], $outputDirectory . '/external-sort-fanin-16.txt');
    }),
];
