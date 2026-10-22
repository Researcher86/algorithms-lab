<?php

declare(strict_types=1);

use AlgorithmsLab\Cache\ArrayLRU;
use AlgorithmsLab\Cache\Cache;
use AlgorithmsLab\Cache\LFUCache;
use AlgorithmsLab\Cache\LRUCache;
use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;

return new Experiment(
    name: 'cache:scan',
    description: 'A sequential scan against warm popular keys: LRU flushes, LFU may keep them.',
    supports: ['capacity', 'iterations', 'seed'],
    run: static function (Options $options, Output $output): void {
        $capacity = $options->capacity(1_000);
        $iterations = $options->iterations(10);
        $seed = $options->seed('scan');
        $popular = [];
        $scan = [];

        for ($i = 0; $i < $capacity; ++$i) {
            $popular[] = sprintf('popular-%s-%d', $seed, $i);
        }

        for ($i = 0; $i < $capacity * 2; ++$i) {
            $scan[] = sprintf('scan-%s-%d', $seed, $i);
        }

        $request = static function (Cache $cache, string $key): void {
            if ($cache->get($key) === null) {
                $cache->put($key, $key);
            }
        };

        $output->heading(
            'Cache — sequential scan',
            'Warm the popular set, request unique scan keys, then request the popular set again.',
        );
        $output->line(sprintf('%-10s %12s %12s %12s', 'policy', 'warm hits', 'scan hits', 'reuse hits'));
        $output->line(str_repeat('-', 50));

        foreach ([
            'LRU' => new LRUCache($capacity),
            'LFU' => new LFUCache($capacity),
            'ArrayLRU' => new ArrayLRU($capacity),
        ] as $name => $cache) {
            $cache->stats()->reset();

            for ($cycle = 0; $cycle < $iterations; ++$cycle) {
                foreach ($popular as $key) {
                    $request($cache, $key);
                }
            }

            $warmRate = $cache->stats()->hitRate();
            $cache->stats()->reset();

            foreach ($scan as $key) {
                $request($cache, $key);
            }

            $scanRate = $cache->stats()->hitRate();
            $cache->stats()->reset();

            foreach ($popular as $key) {
                $request($cache, $key);
            }

            $reuseRate = $cache->stats()->hitRate();

            $output->line(sprintf(
                '%-10s %11.2f%% %11.2f%% %11.2f%%',
                $name,
                $warmRate * 100,
                $scanRate * 100,
                $reuseRate * 100,
            ));

            $output->measure($name . '_warm_hit_rate', $warmRate);
            $output->measure($name . '_scan_hit_rate', $scanRate);
            $output->measure($name . '_reuse_hit_rate', $reuseRate);
        }

        $output->line();
        $output->line('LRU evicts every popular key during the scan; the reuse phase then misses almost everything.');
        $output->line('LFU keeps the high-frequency popular keys because the scan keys enter at frequency one.');
        $output->line('That is a stable-popularity win for LFU, not a universal win: it also has no frequency decay.');
        $output->line(sprintf(
            'Capacity: %s. Warm cycles: %d. Scan keys: %s. Seed: %s.',
            number_format($capacity),
            $iterations,
            number_format(count($scan)),
            $seed,
        ));

        $output->measure('capacity', $capacity);
        $output->measure('iterations', $iterations);
        $output->measure('scan_keys', count($scan));
        $output->measure('seed', $seed);
    },
);
