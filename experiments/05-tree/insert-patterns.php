<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Instrumentation\Measure;
use AlgorithmsLab\Tree\BPlusTree;
use AlgorithmsLab\Tree\BTree;
use AlgorithmsLab\Tree\KeyGenerator;

return new Experiment(
    name: 'tree:insert-patterns',
    description: 'B-tree and B+ tree growth for random, ascending and descending insertion orders.',
    supports: ['elements', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(10_000);
        $degree = max(2, $options->capacity(8));
        $seed = $options->seed('tree-insert-patterns');
        $patterns = [
            'random' => KeyGenerator::shuffled($elements, $seed),
            'ascending' => KeyGenerator::ascending($elements),
            'descending' => KeyGenerator::descending($elements),
        ];

        $output->heading(
            'Tree — insertion patterns',
            'The same keys arrive in different orders. Draws show how splits and leaf links respond.',
        );
        $output->line(sprintf('%-10s %-12s %8s %12s %14s %12s', 'structure', 'pattern', 'height', 'build', 'node reads/op', 'bytes/key'));
        $output->line(str_repeat('-', 76));

        foreach ($patterns as $pattern => $keys) {
            $counters = new Counters();
            $build = new Measure();
            $build->start();
            $tree = new BTree($degree, $counters);

            foreach ($keys as $key) {
                $tree->insert($key);
            }

            $build->stop();
            $tree->validate();
            $height = $tree->height();
            $reads = $counters->nodeReads;
            $bytes = $build->deltaBytes();

            $output->line(sprintf(
                '%-10s %-12s %8d %9.3f ms %14.2f %12.1f',
                'BTree',
                $pattern,
                $height,
                $build->elapsedSeconds() * 1000,
                $reads / $elements,
                $bytes / $elements,
            ));
            $output->measure('btree_' . $pattern . '_height', $height);
            $output->measure('btree_' . $pattern . '_build_seconds', $build->elapsedSeconds());
            $output->measure('btree_' . $pattern . '_node_reads_per_insert', $reads / $elements);
            $output->measure('btree_' . $pattern . '_bytes_per_key', $bytes / $elements);

            unset($tree);
            gc_collect_cycles();
        }

        foreach ($patterns as $pattern => $keys) {
            $counters = new Counters();
            $build = new Measure();
            $build->start();
            $tree = new BPlusTree($degree, $counters);

            foreach ($keys as $key) {
                $tree->insert($key);
            }

            $build->stop();
            $tree->validate();
            $height = $tree->height();
            $reads = $counters->nodeReads;
            $bytes = $build->deltaBytes();

            $output->line(sprintf(
                '%-10s %-12s %8d %9.3f ms %14.2f %12.1f',
                'BPlusTree',
                $pattern,
                $height,
                $build->elapsedSeconds() * 1000,
                $reads / $elements,
                $bytes / $elements,
            ));
            $output->measure('bplus_' . $pattern . '_height', $height);
            $output->measure('bplus_' . $pattern . '_build_seconds', $build->elapsedSeconds());
            $output->measure('bplus_' . $pattern . '_node_reads_per_insert', $reads / $elements);
            $output->measure('bplus_' . $pattern . '_bytes_per_key', $bytes / $elements);

            unset($tree);
            gc_collect_cycles();
        }

        $build = new Measure();
        $build->start();
        $tree = BPlusTree::bulkLoad($degree, KeyGenerator::ascending($elements));
        $build->stop();
        $tree->validate(false);
        $height = $tree->height();
        $bytes = $build->deltaBytes();

        $output->line(sprintf(
            '%-10s %-12s %8d %9.3f ms %14.2f %12.1f',
            'BPlusTree',
            'bulk',
            $height,
            $build->elapsedSeconds() * 1000,
            0.0,
            $bytes / $elements,
        ));
        $output->measure('bplus_bulk_height', $height);
        $output->measure('bplus_bulk_build_seconds', $build->elapsedSeconds());
        $output->measure('bplus_bulk_bytes_per_key', $bytes / $elements);

        $output->line();
        $output->line('Ascending insertion makes every split happen on the right edge; it is not the same shape as random insertion.');
        $output->line('Bulk load packs leaves to capacity and builds internal levels bottom-up, so it skips the search-before-insert work.');
        $output->line(sprintf('Elements: %s. Minimum degree: %d. Seed: %s.', number_format($elements), $degree, $seed));

        $output->measure('elements', $elements);
        $output->measure('minimum_degree', $degree);
        $output->measure('seed', $seed);
    },
);
