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
use AlgorithmsLab\Tree\PageSimulator;
use AlgorithmsLab\Tree\SortedArrayIndex;

return new Experiment(
    name: 'tree:page-reads',
    description: 'B-tree page reads versus binary-search probes on a flat sorted array.',
    supports: ['elements', 'iterations', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(100_000);
        $queries = $options->iterations(1_000);
        $degree = max(2, $options->capacity(16));
        $seed = $options->seed('tree-page-reads');
        $keys = KeyGenerator::shuffled($elements, $seed);
        $ascending = KeyGenerator::ascending($elements);
        $random = new DeterministicRandom($seed . '-queries');
        $queryKeys = [];

        for ($query = 0; $query < $queries; ++$query) {
            $queryKeys[] = $random->intBetween(0, $elements - 1);
        }

        $tree = new BTree($degree);

        foreach ($keys as $key) {
            $tree->insert($key);
        }

        $sortedCounters = new Counters();
        $sorted = SortedArrayIndex::fromSortedArray($ascending, $sortedCounters);

        $output->heading(
            'Tree — page reads versus binary search',
            'The page model charges one access per B-tree node and one page per binary-search probe.',
        );
        $output->line(sprintf('%-12s %8s %16s %16s %14s', 'page size', 'height', 'B-tree pages/query', 'binary comparisons', 'model bytes/key'));
        $output->line(str_repeat('-', 72));

        foreach ([4_096, 8_192] as $pageSize) {
            $pages = new PageSimulator($pageSize);
            $treeMeasure = new Measure();
            $treeMeasure->start();

            foreach ($queryKeys as $key) {
                $tree->search($key, $pages);
            }

            $treeMeasure->stop();
            $treePages = $pages->pagesRead() / $queries;

            $binaryMeasure = new Measure();
            $binaryMeasure->start();
            $sortedCounters->reset();

            foreach ($queryKeys as $key) {
                $sorted->search($key);
            }

            $binaryMeasure->stop();
            $comparisons = $sortedCounters->comparisons / $queries;

            $output->line(sprintf(
                '%-12s %8d %16.2f %16.2f %14.1f',
                number_format($pageSize) . ' B',
                $tree->height(),
                $treePages,
                $comparisons,
                $pages->bytesPerKey(),
            ));
            $output->measure('page_' . $pageSize . '_height', $tree->height());
            $output->measure('page_' . $pageSize . '_tree_pages_per_query', $treePages);
            $output->measure('page_' . $pageSize . '_binary_comparisons_per_query', $comparisons);
            $output->measure('page_' . $pageSize . '_model_bytes_per_key', $pages->bytesPerKey());
        }

        $output->line();
        $output->line('The B-tree pays one node page per level; binary search pays a fresh probe for each comparison.');
        $output->line('This is a model, not an operating-system page cache: it estimates node size from key and pointer widths.');
        $output->line(sprintf('Elements: %s. Queries: %s. Minimum degree: %d. Seed: %s.', number_format($elements), number_format($queries), $degree, $seed));

        $output->measure('elements', $elements);
        $output->measure('queries', $queries);
        $output->measure('minimum_degree', $degree);
        $output->measure('tree_height', $tree->height());
        $output->measure('seed', $seed);
    },
);
