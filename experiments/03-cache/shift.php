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
    name: 'cache:shift',
    description: 'Popularity moves from one key set to another and then back.',
    supports: ['capacity', 'iterations', 'seed'],
    run: static function (Options $options, Output $output): void {
        $capacity = $options->capacity(1_000);
        $iterations = $options->iterations(10);
        $seed = $options->seed('shift');
        $setA = [];
        $setB = [];

        for ($i = 0; $i < $capacity; ++$i) {
            $setA[] = sprintf('hot-a-%s-%d', $seed, $i);
            $setB[] = sprintf('hot-b-%s-%d', $seed, $i);
        }

        $request = static function (Cache $cache, string $key): void {
            if ($cache->get($key) === null) {
                $cache->put($key, $key);
            }
        };

        /**
         * @param list<string> $keys
         */
        $runCycles = static function (Cache $cache, array $keys, int $cycles) use ($request): void {
            for ($cycle = 0; $cycle < $cycles; ++$cycle) {
                foreach ($keys as $key) {
                    $request($cache, $key);
                }
            }
        };

        $output->heading(
            'Cache — popularity shift',
            'A is hot, then B is hot, then A is hot again; LFU has no frequency decay.',
        );
        $output->line(sprintf('%-10s %10s %10s %10s', 'policy', 'A first', 'B next', 'A returns'));
        $output->line(str_repeat('-', 44));

        foreach ([
            'LRU' => new LRUCache($capacity),
            'LFU' => new LFUCache($capacity),
            'ArrayLRU' => new ArrayLRU($capacity),
        ] as $name => $cache) {
            $runCycles($cache, $setA, 1);
            $cache->stats()->reset();
            $runCycles($cache, $setA, $iterations);
            $first = $cache->stats()->hitRate();

            $cache->stats()->reset();
            $runCycles($cache, $setB, $iterations);
            $second = $cache->stats()->hitRate();

            $cache->stats()->reset();
            $runCycles($cache, $setA, $iterations);
            $third = $cache->stats()->hitRate();

            $output->line(sprintf(
                '%-10s %9.2f%% %9.2f%% %9.2f%%',
                $name,
                $first * 100,
                $second * 100,
                $third * 100,
            ));

            $output->measure($name . '_a_first_hit_rate', $first);
            $output->measure($name . '_b_next_hit_rate', $second);
            $output->measure($name . '_a_returns_hit_rate', $third);
        }

        $output->line();
        $output->line('LRU follows the new set: B eventually hits, while A has to be reloaded when it returns.');
        $output->line('LFU keeps A at high frequency, so B keeps evicting itself and A still hits on return.');
        $output->line('This is the opposite trade-off from the scan experiment; neither policy is universally better.');
        $output->line(sprintf(
            'Capacity per set: %s. Cycles per phase: %d. Seed: %s.',
            number_format($capacity),
            $iterations,
            $seed,
        ));

        $output->measure('capacity', $capacity);
        $output->measure('iterations', $iterations);
        $output->measure('seed', $seed);
    },
);
