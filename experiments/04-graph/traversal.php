<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Graph\AdjacencyList;
use AlgorithmsLab\Graph\Bfs;
use AlgorithmsLab\Graph\Components;
use AlgorithmsLab\Graph\Dfs;
use AlgorithmsLab\Graph\GraphGenerator;
use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Instrumentation\Measure;

return new Experiment(
    name: 'graph:traversal',
    description: 'BFS and iterative DFS on a sparse and a denser random graph.',
    supports: ['elements', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $vertices = $options->elements(1_000);
        $sparseEdges = $options->capacity(3_000);
        $seed = $options->seed('traversal');
        $maximum = intdiv($vertices * ($vertices - 1), 2);
        $denseEdges = min($maximum, max($sparseEdges, $vertices * 8));

        $output->heading(
            'Graph — traversal',
            'Both traversals start at vertex 0 on the same generated graph; counters count neighbour reads.',
        );
        $output->line(sprintf(
            '%-8s %8s %9s %12s %12s %12s %10s',
            'density',
            'V',
            'E',
            'BFS time',
            'DFS time',
            'BFS max d',
            'components',
        ));
        $output->line(str_repeat('-', 80));

        foreach ([
            'sparse' => GraphGenerator::random($vertices, $sparseEdges, new DeterministicRandom($seed . '-sparse')),
            'denser' => GraphGenerator::random($vertices, $denseEdges, new DeterministicRandom($seed . '-dense')),
        ] as $name => $edges) {
            $graph = new AdjacencyList($vertices);

            foreach ($edges as $edge) {
                $graph->addEdge($edge->from, $edge->to, $edge->weight);
            }

            $bfsCounters = new Counters();
            $bfsMeasure = new Measure();
            $bfsMeasure->start();
            $bfs = Bfs::run($graph, 0, $bfsCounters);
            $bfsMeasure->stop();

            $dfsCounters = new Counters();
            $dfsMeasure = new Measure();
            $dfsMeasure->start();
            $dfs = Dfs::run($graph, 0, $dfsCounters);
            $dfsMeasure->stop();

            $components = Components::undirected($graph);
            $bfsTime = $bfsMeasure->elapsedSeconds();
            $dfsTime = $dfsMeasure->elapsedSeconds();

            $output->line(sprintf(
                '%-8s %8s %9s %10.3f ms %10.3f ms %12d %10d',
                $name,
                number_format($vertices),
                number_format(count($edges)),
                $bfsTime * 1000,
                $dfsTime * 1000,
                $bfs->maxDistance(),
                count($components),
            ));

            $output->measure($name . '_edges', count($edges));
            $output->measure($name . '_bfs_seconds', $bfsTime);
            $output->measure($name . '_dfs_seconds', $dfsTime);
            $output->measure($name . '_bfs_max_distance', $bfs->maxDistance());
            $output->measure($name . '_components', count($components));
            $output->measure($name . '_bfs_node_reads', $bfsCounters->nodeReads);
            $output->measure($name . '_dfs_node_reads', $dfsCounters->nodeReads);
            $output->measure($name . '_bfs_order', count($bfs->order));
            $output->measure($name . '_dfs_order', count($dfs->order));
        }

        $output->line();
        $output->line('Components use BFS internally, so the component count is the number of BFS forests needed to cover V.');
        $output->line('The DFS order and BFS order differ, but both must reach the same component.');
        $output->line('A denser graph does more neighbour work per vertex; the asymptotic O(V + E) still holds.');
        $output->line(sprintf('Seed: %s. Vertices: %s. Sparse edges: %s. Dense edges: %s.', $seed, number_format($vertices), number_format($sparseEdges), number_format($denseEdges)));

        $output->measure('vertices', $vertices);
        $output->measure('sparse_edges_requested', $sparseEdges);
        $output->measure('dense_edges_requested', $denseEdges);
        $output->measure('seed', $seed);
    },
);
