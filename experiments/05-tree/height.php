<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Instrumentation\Measure;
use AlgorithmsLab\Tree\BTree;
use AlgorithmsLab\Tree\KeyGenerator;

return new Experiment(
    name: 'tree:height',
    description: 'B-tree height, fanout and search work as the minimum degree changes.',
    supports: ['elements', 'iterations', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(50_000);
        $queries = $options->iterations(1_000);
        $seed = $options->seed('tree-height');
        $keys = KeyGenerator::shuffled($elements, $seed);
        $random = new DeterministicRandom($seed . '-queries');
        $queryKeys = [];

        for ($query = 0; $query < $queries; ++$query) {
            $queryKeys[] = $random->intBetween(0, $elements - 1);
        }

        $output->heading(
            'Tree — height and fanout',
            'Insert the same shuffled keys into B-trees with different minimum degrees, then search random keys.',
        );
        $output->line(sprintf('%-5s %8s %14s %12s %16s', 't', 'height', 'bytes/key', 'build', 'search reads'));
        $output->line(str_repeat('-', 61));

        foreach ([4, 8, 16, 32] as $degree) {
            $counters = new Counters();
            $build = new Measure();
            $build->start();
            $tree = new BTree($degree, $counters);

            foreach ($keys as $key) {
                $tree->insert($key);
            }

            $build->stop();
            $tree->validate();
            $bytes = $build->deltaBytes();

            $counters->reset();
            $search = new Measure();
            $search->start();

            foreach ($queryKeys as $key) {
                $tree->search($key);
            }

            $search->stop();
            $reads = $counters->nodeReads;
            $height = $tree->height();

            $output->line(sprintf(
                '%-5d %8d %14.1f %9.3f ms %16.2f',
                $degree,
                $height,
                $bytes / $elements,
                $build->elapsedSeconds() * 1000,
                $reads / $queries,
            ));
            $output->measure('t' . $degree . '_height', $height);
            $output->measure('t' . $degree . '_bytes_per_key', $bytes / $elements);
            $output->measure('t' . $degree . '_build_seconds', $build->elapsedSeconds());
            $output->measure('t' . $degree . '_search_node_reads', $reads);
            $output->measure('t' . $degree . '_search_node_reads_per_query', $reads / $queries);

            unset($tree);
            gc_collect_cycles();
        }

        $output->line();
        $output->line('A larger minimum degree means a wider, shallower tree: fewer node reads per search, more keys per node.');
        $output->line('The bytes/key column includes the node objects and their PHP array overhead, not a disk page size.');
        $output->line(sprintf('Elements: %s. Queries: %s. Seed: %s.', number_format($elements), number_format($queries), $seed));

        $output->measure('elements', $elements);
        $output->measure('queries', $queries);
        $output->measure('seed', $seed);
    },
);
