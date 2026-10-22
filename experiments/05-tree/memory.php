<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Instrumentation\Measure;
use AlgorithmsLab\Tree\BPlusTree;
use AlgorithmsLab\Tree\BTree;
use AlgorithmsLab\Tree\KeyGenerator;
use AlgorithmsLab\Tree\PhpArrayIndex;
use AlgorithmsLab\Tree\SortedArrayIndex;

return new Experiment(
    name: 'tree:memory',
    description: 'PHP-allocator bytes per key for a B-tree, a B+ tree and two flat baselines.',
    supports: ['elements', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(50_000);
        $degree = max(2, $options->capacity(8));
        $seed = $options->seed('tree-memory');
        $shuffled = KeyGenerator::shuffled($elements, $seed);
        $ascending = KeyGenerator::ascending($elements);
        $report = static function (Output $output, string $structure, string $height, Measure $measure, int $elements): void {
            $bytes = $measure->deltaBytes();
            $name = strtolower(str_replace([' ', '(', ')'], ['_', '', ''], $structure));

            $output->line(sprintf(
                '%-18s %8s %11.1f KiB %12.1f %9.3f ms',
                $structure,
                $height,
                $bytes / 1024,
                $bytes / $elements,
                $measure->elapsedSeconds() * 1000,
            ));
            $output->measure($name . '_bytes', $bytes);
            $output->measure($name . '_bytes_per_key', $bytes / $elements);
            $output->measure($name . '_build_seconds', $measure->elapsedSeconds());
        };

        $output->heading(
            'Tree — bytes per key',
            'The keys are the same; only the representation changes. These are PHP allocator deltas, not disk sizes.',
        );
        $output->line(sprintf('%-18s %8s %14s %12s %12s', 'structure', 'height', 'PHP memory', 'bytes/key', 'build'));
        $output->line(str_repeat('-', 68));

        $measure = new Measure();
        $measure->start();
        $btree = new BTree($degree);

        foreach ($shuffled as $key) {
            $btree->insert($key);
        }

        $measure->stop();
        $btree->validate();
        $report($output, 'BTree', (string) $btree->height(), $measure, $elements);
        unset($btree);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $bplus = BPlusTree::bulkLoad($degree, $ascending);
        $measure->stop();
        $bplus->validate(false);
        $report($output, 'BPlusTree (bulk)', (string) $bplus->height(), $measure, $elements);
        unset($bplus);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $sorted = SortedArrayIndex::fromSortedArray($ascending);
        $measure->stop();
        $report($output, 'SortedArrayIndex', 'n/a', $measure, $elements);
        unset($sorted);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $phpArray = new PhpArrayIndex();

        foreach ($ascending as $key) {
            $phpArray->insert($key);
        }

        $measure->stop();
        $report($output, 'PhpArrayIndex', 'n/a', $measure, $elements);

        $output->line();
        $output->line('The B-tree and B+ tree pay for explicit nodes and child arrays; the flat baselines pay less memory but cannot answer ranges cheaply.');
        $output->line('The B+ bulk row skips insertion; its memory is the packed tree, not a tree built one key at a time.');
        $output->line(sprintf('Elements: %s. Minimum degree: %d. Seed: %s.', number_format($elements), $degree, $seed));

        $output->measure('elements', $elements);
        $output->measure('minimum_degree', $degree);
        $output->measure('seed', $seed);
    },
);
