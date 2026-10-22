<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\HashTable\ChainingHashTable;
use AlgorithmsLab\HashTable\OpenAddressingHashTable;
use AlgorithmsLab\HashTable\ProbeStrategy;

return new Experiment(
    name: 'hash:load-factor',
    description: 'Average probes for hits and misses at several load factors.',
    supports: ['elements', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(20_000);
        $seed = $options->seed('load-factor');
        $keys = [];
        $missing = [];

        for ($i = 0; $i < $elements; ++$i) {
            $keys[] = sprintf('present-%s-%d', $seed, $i);
            $missing[] = sprintf('missing-%s-%d', $seed, $i);
        }

        $previousPowerOfTwo = static function (int $value): int {
            $power = 1;

            while ($power * 2 <= $value) {
                $power *= 2;
            }

            return $power;
        };

        $output->heading(
            'Hash table — load factor',
            'The same number of keys is inserted at each target load factor; the table reports actual alpha.',
        );
        $output->line('Average probes per successful and unsuccessful lookup. Lower is better.');
        $output->line();
        $output->line(sprintf(
            '%-10s %7s %9s %9s %10s %10s',
            'table',
            'alpha',
            'items',
            'capacity',
            'hit',
            'miss',
        ));
        $output->line(str_repeat('-', 62));

        foreach ([0.50, 0.75, 0.90] as $alpha) {
            $capacity = max(1, $previousPowerOfTwo((int) ceil($elements / $alpha)));
            $size = min($elements, max(1, (int) floor($capacity * $alpha)));
            $alphaLabel = number_format($alpha, 2, '_', '');

            $tables = [
                'chaining' => new ChainingHashTable($capacity, maxLoadFactor: 1.0),
                'linear' => new OpenAddressingHashTable($capacity, ProbeStrategy::Linear, maxLoadFactor: 1.0),
                'quadratic' => new OpenAddressingHashTable($capacity, ProbeStrategy::Quadratic, maxLoadFactor: 1.0),
                'double' => new OpenAddressingHashTable($capacity, ProbeStrategy::DoubleHashing, maxLoadFactor: 1.0),
            ];

            foreach ($tables as $label => $table) {
                for ($i = 0; $i < $size; ++$i) {
                    $table->put($keys[$i], $i);
                }

                $counters = $table->counters();
                $counters->reset();

                for ($i = 0; $i < $size; ++$i) {
                    $table->get($keys[$i]);
                }

                $hitProbes = $counters->probes / $size;
                $counters->reset();

                for ($i = 0; $i < $size; ++$i) {
                    $table->get($missing[$i]);
                }

                $missProbes = $counters->probes / $size;
                $actualAlpha = $table->loadFactor();

                $output->line(sprintf(
                    '%-10s %7.3f %9s %9s %10.3f %10.3f',
                    $label,
                    $actualAlpha,
                    number_format($table->count()),
                    number_format($table->capacity()),
                    $hitProbes,
                    $missProbes,
                ));

                $output->measure(sprintf('%s_%s_hit_probes', $label, $alphaLabel), $hitProbes);
                $output->measure(sprintf('%s_%s_miss_probes', $label, $alphaLabel), $missProbes);
                $output->measure(sprintf('%s_%s_load_factor', $label, $alphaLabel), $actualAlpha);
            }

            $output->line();
        }

        $output->line('A miss must probe until an empty slot; a hit can stop at the key.');
        $output->line('That asymmetry is why the miss column grows faster than the hit column.');
        $output->line();
        $output->line(sprintf('Seed: %s. Keys: %s. Missing keys: %s.', $seed, count($keys), count($missing)));

        $output->measure('elements', $elements);
        $output->measure('seed', $seed);
        $output->measure('missing_keys', count($missing));
    },
);
