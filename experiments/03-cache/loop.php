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
    name: 'cache:loop',
    description: 'A cyclic scan just below, at and above capacity.',
    supports: ['capacity', 'iterations', 'seed'],
    run: static function (Options $options, Output $output): void {
        $capacity = $options->capacity(100);
        $iterations = $options->iterations(5);
        $seed = $options->seed('loop');
        $lengths = array_values(array_unique([
            max(1, $capacity - 1),
            $capacity,
            $capacity + 1,
            $capacity * 2,
        ]));

        $request = static function (Cache $cache, string $key): void {
            if ($cache->get($key) === null) {
                $cache->put($key, $key);
            }
        };

        $output->heading(
            'Cache — cyclic scan',
            'Each loop is warmed once, then measured for several cycles; every miss is followed by a put.',
        );
        $output->line(sprintf('%-8s %10s %10s %10s', 'length', 'LRU', 'LFU', 'ArrayLRU'));
        $output->line(str_repeat('-', 44));

        foreach ($lengths as $length) {
            $keys = [];

            for ($i = 0; $i < $length; ++$i) {
                $keys[] = sprintf('loop-%s-%d', $seed, $i);
            }

            $rates = [];

            foreach ([
                'lru' => new LRUCache($capacity),
                'lfu' => new LFUCache($capacity),
                'array' => new ArrayLRU($capacity),
            ] as $name => $cache) {
                foreach ($keys as $key) {
                    $request($cache, $key);
                }

                $cache->stats()->reset();

                for ($cycle = 0; $cycle < $iterations; ++$cycle) {
                    foreach ($keys as $key) {
                        $request($cache, $key);
                    }
                }

                $rate = $cache->stats()->hitRate();
                $rates[$name] = $rate;
                $output->measure(sprintf('loop_%d_%s_hit_rate', $length, $name), $rate);
            }

            $relative = $length <= $capacity ? 'fits' : 'larger than capacity';

            $output->line(sprintf(
                '%-8d %9.2f%% %9.2f%% %9.2f%%  (%s)',
                $length,
                $rates['lru'] * 100,
                $rates['lfu'] * 100,
                $rates['array'] * 100,
                $relative,
            ));
        }

        $output->line();
        $output->line('A loop of capacity + 1 is the LRU worst case: every request misses.');
        $output->line('LFU cannot help when all frequencies are equal; its LRU tie-break produces the same misses.');
        $output->line(sprintf('Capacity: %s. Measured cycles: %d. Seed: %s.', number_format($capacity), $iterations, $seed));

        $output->measure('capacity', $capacity);
        $output->measure('iterations', $iterations);
        $output->measure('seed', $seed);
    },
);
