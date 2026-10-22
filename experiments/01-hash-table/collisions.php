<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\HashTable\ChainingHashTable;
use AlgorithmsLab\HashTable\HashFunction;

return new Experiment(
    name: 'hash:collisions',
    description: 'Bucket distribution for four hash functions at a fixed capacity.',
    supports: ['elements', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(10_000);
        $capacity = $options->capacity(1_024);
        $seed = $options->seed('collisions');
        $keys = [];

        for ($i = 0; $i < $elements; ++$i) {
            $keys[] = sprintf('present-%s-%d', $seed, $i);
        }

        $functions = [
            HashFunction::fnv1a64(),
            HashFunction::xxh128(),
            HashFunction::crc32(),
            HashFunction::murmur3(),
        ];

        $output->heading(
            'Hash table — collisions',
            sprintf(
                'Keys are inserted into a fixed %s-bucket table with automatic resizing disabled.',
                number_format($capacity),
            ),
        );
        $output->line(sprintf(
            '%-14s %8s %10s %8s %10s %10s',
            'hash',
            'max',
            'empty',
            'mean',
            'stddev',
            'chi2',
        ));
        $output->line(str_repeat('-', 68));

        foreach ($functions as $function) {
            $table = new ChainingHashTable(
                capacity: $capacity,
                hashFunction: $function,
                maxLoadFactor: 1.0,
                autoResize: false,
            );

            foreach ($keys as $index => $key) {
                $table->put($key, $index);
            }

            $lengths = $table->bucketLengths();
            $mean = $elements / $capacity;
            $empty = 0;
            $sumSquares = 0.0;
            $chiSquare = 0.0;

            foreach ($lengths as $length) {
                if ($length === 0) {
                    ++$empty;
                }

                $sumSquares += ($length - $mean) ** 2;
                $chiSquare += (($length - $mean) ** 2) / $mean;
            }

            $stddev = sqrt($sumSquares / $capacity);
            $maximum = $table->maxBucketLength();

            $output->line(sprintf(
                '%-14s %8d %10d %8.3f %10.3f %10.3f',
                $function->name,
                $maximum,
                $empty,
                $mean,
                $stddev,
                $chiSquare,
            ));

            $output->measure($function->name . '_max_bucket', $maximum);
            $output->measure($function->name . '_empty_buckets', $empty);
            $output->measure($function->name . '_stddev', $stddev);
            $output->measure($function->name . '_chi_square', $chiSquare);
            $output->measure($function->name . '_collisions', $table->counters()->collisions);
        }

        $output->line();
        $output->line(sprintf(
            'Capacity: %s. Elements: %s. Load factor: %.1f. Seed: %s.',
            number_format($capacity),
            number_format($elements),
            $elements / $capacity,
            $seed,
        ));
        $output->line('A good hash fills buckets evenly even when the load factor is far above one.');
        $output->line('Automatic resizing is off so the distribution, not the growth policy, is measured.');

        $output->measure('elements', $elements);
        $output->measure('capacity', $capacity);
        $output->measure('seed', $seed);
    },
);
