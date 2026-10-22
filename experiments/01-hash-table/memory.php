<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\ByteFormatter;
use AlgorithmsLab\HashTable\ChainingHashTable;
use AlgorithmsLab\HashTable\OpenAddressingHashTable;
use AlgorithmsLab\Instrumentation\Measure;

return new Experiment(
    name: 'hash:memory',
    description: 'PHP-allocator cost of chaining, open addressing and a plain array.',
    supports: ['elements', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(20_000);
        $seed = $options->seed('memory');
        $keys = [];

        for ($i = 0; $i < $elements; ++$i) {
            $keys[] = sprintf('memory-%s-%d', $seed, $i);
        }

        $output->heading(
            'Hash table — memory',
            'The same keys and integer values, measured through memory_get_usage(false).',
        );
        $output->line('PHP allocator only; this is not RSS and not a prediction for another language.');
        $output->line();
        $output->line(sprintf('%-16s %14s %14s %10s', 'representation', 'PHP memory', 'bytes/key', 'capacity'));
        $output->line(str_repeat('-', 60));

        $nextPowerOfTwo = static function (int $value): int {
            $power = 1;

            while ($power < $value) {
                $power *= 2;
            }

            return $power;
        };

        $chainingCapacity = max(1, (int) ceil($elements / 4));
        $openCapacity = $nextPowerOfTwo(max(1, (int) ceil($elements / 0.75)));

        $chainingMeasure = new Measure();
        $chainingMeasure->start();
        $chaining = new ChainingHashTable(
            capacity: $chainingCapacity,
            maxLoadFactor: 1.0,
            autoResize: false,
        );

        foreach ($keys as $index => $key) {
            $chaining->put($key, $index);
        }

        $chainingMeasure->stop();
        $chainingBytes = $chainingMeasure->deltaBytes();

        $output->line(sprintf(
            '%-16s %14s %14.1f %10s',
            'chaining',
            ByteFormatter::format($chainingBytes),
            $chainingBytes / $elements,
            number_format($chaining->capacity()),
        ));
        $output->measure('chaining_bytes', $chainingBytes);
        $output->measure('chaining_bytes_per_key', $chainingBytes / $elements);
        $output->measure('chaining_capacity', $chaining->capacity());

        unset($chaining);
        gc_collect_cycles();

        $openMeasure = new Measure();
        $openMeasure->start();
        $open = new OpenAddressingHashTable(
            capacity: $openCapacity,
            maxLoadFactor: 1.0,
        );

        foreach ($keys as $index => $key) {
            $open->put($key, $index);
        }

        $openMeasure->stop();
        $openBytes = $openMeasure->deltaBytes();

        $output->line(sprintf(
            '%-16s %14s %14.1f %10s',
            'open addressing',
            ByteFormatter::format($openBytes),
            $openBytes / $elements,
            number_format($open->capacity()),
        ));
        $output->measure('open_bytes', $openBytes);
        $output->measure('open_bytes_per_key', $openBytes / $elements);
        $output->measure('open_capacity', $open->capacity());

        unset($open);
        gc_collect_cycles();

        $arrayMeasure = new Measure();
        $arrayMeasure->start();
        $array = [];

        foreach ($keys as $index => $key) {
            $array[$key] = $index;
        }

        $arrayMeasure->stop();
        $arrayBytes = $arrayMeasure->deltaBytes();

        $output->line(sprintf(
            '%-16s %14s %14.1f %10s',
            'PHP array',
            ByteFormatter::format($arrayBytes),
            $arrayBytes / $elements,
            number_format(count($array)),
        ));
        $output->measure('php_array_bytes', $arrayBytes);
        $output->measure('php_array_bytes_per_key', $arrayBytes / $elements);
        $output->measure('php_array_count', count($array));

        $output->line();
        $output->line('Chaining pays for one Entry object per key plus bucket arrays.');
        $output->line('Open addressing pays for three parallel slot arrays and leaves empty slots to keep probing short.');
        $output->line('A PHP array is already a hash table; it is the baseline, not a straw man.');
        $output->line(sprintf('Seed: %s. Elements: %s.', $seed, number_format($elements)));

        $output->measure('elements', $elements);
        $output->measure('seed', $seed);
    },
);
