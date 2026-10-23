<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Stream\MisraGries;
use AlgorithmsLab\Stream\SpaceSaving;
use AlgorithmsLab\Stream\StreamGenerator;

return new Experiment(
    name: 'stream:drift',
    description: 'How bounded summaries react when the hot set changes halfway through the stream.',
    supports: ['elements', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(100_000);
        $capacity = max(1, $options->capacity(30));
        $seed = $options->seed('stream-drift');
        $stream = StreamGenerator::drift($elements, 200, new DeterministicRandom($seed));
        $counts = [];

        foreach ($stream as $key) {
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $currentHot = [];

        foreach (array_keys($counts) as $key) {
            if (str_starts_with($key, 'phase-b-')) {
                $currentHot[$key] = true;
            }
        }

        $output->heading(
            'Stream — concept drift',
            'The first half favors phase-a keys; the second half favors phase-b. A bounded summary cannot keep everything.',
        );
        $output->line(sprintf('%-14s %10s %18s %16s %14s', 'algorithm', 'entries', 'current-hot recall', 'stale A in top', 'top count'));
        $output->line(str_repeat('-', 76));

        foreach ([
            'space-saving' => new SpaceSaving($capacity),
            'misra-gries' => new MisraGries($capacity),
        ] as $name => $summary) {
            foreach ($stream as $key) {
                $summary->observe($key);
            }

            $top = $summary->top($capacity);
            $topKeys = array_column($top, 'key');
            $currentHits = count(array_intersect(array_keys($currentHot), $topKeys));
            $stale = 0;

            foreach ($topKeys as $key) {
                if (str_starts_with($key, 'phase-a-')) {
                    ++$stale;
                }
            }

            $output->line(sprintf(
                '%-14s %10d %17.1f%% %16d %14d',
                $name,
                $summary->size(),
                $currentHits / max(1, count($currentHot)) * 100,
                $stale,
                $top[0]['count'] ?? 0,
            ));
            $output->measure($name . '_current_hot_recall', $currentHits / max(1, count($currentHot)));
            $output->measure($name . '_stale_keys', $stale);
            $output->measure($name . '_top_count', $top[0]['count'] ?? 0);
        }

        $output->line();
        $output->line('Both summaries kept old phase-A keys: a bounded summary is not a sliding window.');
        $output->line('Space-Saving replaces the current minimum; Misra-Gries cancels only when its set is full, so a large old counter can survive.');
        $output->line('A high count in the table is an estimate, not a guarantee that the key is still popular.');
        $output->line(sprintf('Items: %s. Capacity: %d. Current hot set: %d keys. Seed: %s.', number_format($elements), $capacity, count($currentHot), $seed));

        $output->measure('elements', $elements);
        $output->measure('capacity', $capacity);
        $output->measure('current_hot_set', count($currentHot));
        $output->measure('seed', $seed);
    },
);
