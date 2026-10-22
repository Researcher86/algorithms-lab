<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Instrumentation\Measure;
use AlgorithmsLab\Tree\BPlusTree;
use AlgorithmsLab\Tree\KeyGenerator;

return new Experiment(
    name: 'tree:range',
    description: 'B+ tree linked-leaf range scans for three result sizes.',
    supports: ['elements', 'iterations', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(100_000);
        $queries = $options->iterations(200);
        $degree = max(2, $options->capacity(16));
        $seed = $options->seed('tree-range');
        $keys = KeyGenerator::ascending($elements);
        $random = new DeterministicRandom($seed . '-queries');
        $counters = new Counters();

        $build = new Measure();
        $build->start();
        $tree = BPlusTree::bulkLoad($degree, $keys, $counters);
        $build->stop();
        $tree->validate(false);

        $output->heading(
            'Tree — range scans',
            'A bulk-loaded B+ tree answers ranges by walking linked leaves, not by returning to the root.',
        );
        $output->line(sprintf('%-12s %12s %12s %16s %16s', 'range size', 'avg keys', 'time/query', 'node reads/query', 'total results'));
        $output->line(str_repeat('-', 74));

        foreach (array_unique([10, 100, 1_000]) as $wantedSize) {
            $size = min($wantedSize, $elements);
            $maxStart = max(0, $elements - $size);
            $counters->reset();
            $measure = new Measure();
            $measure->start();
            $results = 0;

            for ($query = 0; $query < $queries; ++$query) {
                $start = $random->intBetween(0, $maxStart);
                $results += count($tree->range($start, $start + $size - 1));
            }

            $measure->stop();
            $reads = $counters->nodeReads;
            $seconds = $measure->elapsedSeconds();

            $output->line(sprintf(
                '%-12d %12d %9.3f us %14.2f %16d',
                $size,
                $results / $queries,
                $seconds * 1_000_000 / $queries,
                $reads / $queries,
                $results,
            ));
            $output->measure('range_' . $size . '_average_keys', $results / $queries);
            $output->measure('range_' . $size . '_seconds', $seconds);
            $output->measure('range_' . $size . '_node_reads_per_query', $reads / $queries);
            $output->measure('range_' . $size . '_total_results', $results);
        }

        $output->line();
        $output->line('Node reads grow with result size only when the range crosses leaf boundaries; the internal path is paid once per query.');
        $output->line('The build time below is the one-pass bulk load, not repeated insertion.');
        $output->line(sprintf(
            'Elements: %s. Queries per size: %s. Build: %.3f ms. Minimum degree: %d. Seed: %s.',
            number_format($elements),
            number_format($queries),
            $build->elapsedSeconds() * 1000,
            $degree,
            $seed,
        ));

        $output->measure('elements', $elements);
        $output->measure('queries', $queries);
        $output->measure('minimum_degree', $degree);
        $output->measure('build_seconds', $build->elapsedSeconds());
        $output->measure('build_bytes', $build->deltaBytes());
        $output->measure('seed', $seed);
    },
);
