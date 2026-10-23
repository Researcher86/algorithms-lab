<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Stream\CountMinSketch;
use AlgorithmsLab\Stream\MisraGries;
use AlgorithmsLab\Stream\QuickSelect;
use AlgorithmsLab\Stream\SpaceSaving;
use AlgorithmsLab\Stream\StreamGenerator;

return new Experiment(
    name: 'stream:heavy-hitters',
    description: 'Recall and frequency error for bounded heavy-hitter summaries on a Zipf stream.',
    supports: ['elements', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(100_000);
        $items = min($elements, 10_000);
        $seed = $options->seed('stream-heavy-hitters');
        $stream = StreamGenerator::zipf($elements, $items, 1.1, new DeterministicRandom($seed));
        $counts = [];

        foreach ($stream as $key) {
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $keys = static fn (array $top): array => array_column($top, 'key');
        $rankError = static function (array $trueTop, array $candidateTop, int $k): float {
            $candidateRanks = [];

            foreach ($candidateTop as $index => $entry) {
                $candidateRanks[$entry['key']] = $index + 1;
            }

            $error = 0;

            foreach ($trueTop as $index => $entry) {
                $candidateRank = $candidateRanks[$entry['key']] ?? ($k + 1);
                $error += abs($candidateRank - ($index + 1));
            }

            return $error / max(1, count($trueTop));
        };
        $meanError = static function (array $trueTop, callable $estimate, array $exact): float {
            $error = 0;

            foreach ($trueTop as $entry) {
                $error += abs($estimate($entry['key']) - $exact[$entry['key']]);
            }

            return $error / max(1, count($trueTop));
        };
        $maximumOverestimate = static function (array $trueTop, callable $estimate, array $exact): int {
            $maximum = 0;

            foreach ($trueTop as $entry) {
                $maximum = max($maximum, $estimate($entry['key']) - $exact[$entry['key']]);
            }

            return $maximum;
        };

        $output->heading(
            'Stream — heavy hitters',
            'A detector must find the keys; an estimator only scores keys it is given. The two tables keep that difference visible.',
        );
        $output->line('Detectors at capacity K:');
        $output->line(sprintf('%-6s %-14s %10s %10s %11s %14s', 'K', 'algorithm', 'capacity', 'recall', 'precision', 'mean rank err'));
        $output->line(str_repeat('-', 73));

        foreach ([10, 100, 1_000] as $k) {
            $trueTop = QuickSelect::top($counts, $k);
            $trueKeys = $keys($trueTop);
            $trueSet = array_fill_keys($trueKeys, true);

            foreach (['space-saving' => new SpaceSaving($k), 'misra-gries' => new MisraGries($k)] as $name => $summary) {
                foreach ($stream as $key) {
                    $summary->observe($key);
                }

                $candidateTop = $summary->top($k);
                $candidateKeys = $keys($candidateTop);
                $hits = count(array_intersect($trueKeys, $candidateKeys));

                $output->line(sprintf(
                    '%-6d %-14s %10d %9.1f%% %10.1f%% %14.2f',
                    $k,
                    $name,
                    $summary->size(),
                    $hits / max(1, count($trueKeys)) * 100,
                    $hits / max(1, count($candidateKeys)) * 100,
                    $rankError($trueTop, $candidateTop, $k),
                ));
                $output->measure($name . '_k' . $k . '_recall', $hits / max(1, count($trueKeys)));
                $output->measure($name . '_k' . $k . '_precision', $hits / max(1, count($candidateKeys)));
                $output->measure($name . '_k' . $k . '_rank_error', $rankError($trueTop, $candidateTop, $k));
            }
        }

        $output->line();
        $output->line('Frequency estimators on the true top-K keys (equal counter budget: 4K counters/cells):');
        $output->line(sprintf('%-6s %-16s %14s %12s %14s', 'K', 'estimator', 'counter units', 'mean error', 'max overestimate'));
        $output->line(str_repeat('-', 70));

        foreach ([10, 100, 1_000] as $k) {
            $trueTop = QuickSelect::top($counts, $k);
            $budget = 4 * $k;
            $countMin = new CountMinSketch($k, 4);
            $spaceSaving = new SpaceSaving($budget);
            $misraGries = new MisraGries($budget);

            foreach ($stream as $key) {
                $countMin->add($key);
                $spaceSaving->observe($key);
                $misraGries->observe($key);
            }

            $estimators = [
                'count-min' => [$countMin->estimate(...), 'cells', $countMin->cells()],
                'space-saving' => [$spaceSaving->estimate(...), 'entries', $spaceSaving->size()],
                'misra-gries' => [$misraGries->estimate(...), 'entries', $misraGries->size()],
            ];

            foreach ($estimators as $name => [$estimate, $unit, $units]) {
                $error = $meanError($trueTop, $estimate, $counts);
                $maximum = $maximumOverestimate($trueTop, $estimate, $counts);

                $output->line(sprintf(
                    '%-6d %-16s %8d %-5s %11.2f %14d',
                    $k,
                    $name,
                    $units,
                    $unit,
                    $error,
                    $maximum,
                ));
                $output->measure($name . '_k' . $k . '_mean_error', $error);
                $output->measure($name . '_k' . $k . '_max_overestimate', $maximum);
            }
        }

        $output->line();
        $output->line('Count-Min cannot list its own keys: the table above gives it the true keys and scores only the estimates.');
        $output->line('Space-Saving overestimates survivors; Misra-Gries underestimates survivors. Their detector recall is the useful comparison.');
        $output->line(sprintf('Elements: %s. Zipf alpha: 1.1. Distinct keys: %s. Seed: %s.', number_format($elements), number_format(count($counts)), $seed));

        $output->measure('elements', $elements);
        $output->measure('distinct', count($counts));
        $output->measure('seed', $seed);
    },
);
