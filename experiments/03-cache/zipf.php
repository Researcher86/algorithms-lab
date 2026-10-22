<?php

declare(strict_types=1);

use AlgorithmsLab\Cache\ArrayLRU;
use AlgorithmsLab\Cache\Cache;
use AlgorithmsLab\Cache\LFUCache;
use AlgorithmsLab\Cache\LRUCache;
use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Foundation\ZipfDistribution;

return new Experiment(
    name: 'cache:zipf',
    description: 'Hit ratio on Zipf workloads at three capacities and three exponents.',
    supports: ['elements', 'iterations', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(100_000);
        $iterations = $options->iterations(20_000);
        $seed = $options->seed('zipf');
        $capacities = [100, 1_000, 10_000];
        /**
         * @param list<string> $requests
         */
        $replay = static function (Cache $cache, array $requests): void {
            foreach ($requests as $key) {
                if ($cache->get($key) === null) {
                    $cache->put($key, $key);
                }
            }
        };

        $output->heading(
            'Cache — Zipf hit ratio',
            'A request sequence is generated once per exponent and replayed against each policy and capacity.',
        );
        $output->line(sprintf(
            '%-6s %9s %10s %10s %10s',
            'alpha',
            'capacity',
            'LRU',
            'LFU',
            'ArrayLRU',
        ));
        $output->line(str_repeat('-', 50));

        foreach ([0.8, 1.0, 1.2] as $alpha) {
            $zipf = new ZipfDistribution($elements, $alpha, new DeterministicRandom($seed . '-' . $alpha));
            $requests = [];
            $alphaLabel = number_format($alpha, 1, '_', '');

            for ($i = 0; $i < $iterations; ++$i) {
                $requests[] = 'key-' . $zipf->next();
            }

            foreach ($capacities as $capacity) {
                $caches = [
                    'lru' => new LRUCache($capacity),
                    'lfu' => new LFUCache($capacity),
                    'array' => new ArrayLRU($capacity),
                ];
                $rates = ['lru' => 0.0, 'lfu' => 0.0, 'array' => 0.0];

                foreach ($caches as $name => $cache) {
                    $replay($cache, $requests);
                    $rate = $cache->stats()->hitRate();
                    $rates[$name] = $rate;

                    $output->measure(
                        sprintf('zipf_%s_cap%d_%s_hit_rate', $alphaLabel, $capacity, $name),
                        $rate,
                    );
                }

                $output->line(sprintf(
                    '%-6.1f %9s %9.2f%% %9.2f%% %9.2f%%',
                    $alpha,
                    number_format($capacity),
                    $rates['lru'] * 100,
                    $rates['lfu'] * 100,
                    $rates['array'] * 100,
                ));
            }

            $output->line();
        }

        $output->line('Hit rate counts only get() calls; a miss is followed by put() to model a backing store.');
        $output->line('LFU has no frequency decay, so stable popularity favors it.');
        $output->line('ArrayLRU is the PHP-array baseline; its hit ratio should match LRU.');
        $output->line('NaiveLRU is omitted here because its hit ratio is also the same and its scan cost would dominate.');
        $output->line(sprintf(
            'Seed: %s. Keyspace: %s. Requests per cell: %s.',
            $seed,
            number_format($elements),
            number_format($iterations),
        ));

        $output->measure('elements', $elements);
        $output->measure('iterations', $iterations);
        $output->measure('seed', $seed);
    },
);
