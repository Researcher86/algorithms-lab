<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\UnionFind;
use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Instrumentation\Measure;

return new Experiment(
    name: 'graph:dsu',
    description: 'Union-find variants on a deliberately long chain, then repeated finds.',
    supports: ['elements', 'iterations'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(10_000);
        $finds = $options->iterations(1_000);

        $output->heading(
            'Graph — union-find variants',
            'Build a chain 0–1–…–n−1, reset the counter, then run repeated finds of the deepest element.',
        );
        $output->line(sprintf('%-18s %12s %14s %16s', 'variant', 'time', 'node reads/find', 'total node reads'));
        $output->line(str_repeat('-', 66));

        foreach ([
            'compression+rank' => [true, true],
            'compression-only' => [true, false],
            'rank-only' => [false, true],
            'naive' => [false, false],
        ] as $name => [$compression, $rank]) {
            $counters = new Counters();
            $sets = new UnionFind($compression, $rank, $counters);

            for ($element = 0; $element < $elements; ++$element) {
                $sets->makeSet($element);
            }

            for ($element = 1; $element < $elements; ++$element) {
                $sets->union($element, $element - 1);
            }

            $counters->reset();
            $measure = new Measure();
            $measure->start();

            for ($iteration = 0; $iteration < $finds; ++$iteration) {
                $sets->find(0);
            }

            $measure->stop();
            $seconds = $measure->elapsedSeconds();
            $reads = $counters->nodeReads;
            $output->line(sprintf(
                '%-18s %9.3f ms %14.2f %16d',
                $name,
                $seconds * 1000,
                $reads / $finds,
                $reads,
            ));
            $output->measure($name . '_seconds', $seconds);
            $output->measure($name . '_node_reads', $reads);
            $output->measure($name . '_node_reads_per_find', $reads / $finds);
        }

        $output->line();
        $output->line('The chain is a worst case for a union that always attaches the right root to the left root.');
        $output->line('Union by rank alone keeps the chain shallow; compression alone fixes the first find and then keeps it cheap.');
        $output->line('The naive variant keeps paying for the full path on every find.');
        $output->line(sprintf('Elements: %s. Finds: %s.', number_format($elements), number_format($finds)));

        $output->measure('elements', $elements);
        $output->measure('finds', $finds);
    },
);
