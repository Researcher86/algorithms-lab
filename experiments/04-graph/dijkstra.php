<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Graph\AdjacencyList;
use AlgorithmsLab\Graph\BellmanFord;
use AlgorithmsLab\Graph\Dijkstra;
use AlgorithmsLab\Graph\DijkstraDecreaseKey;
use AlgorithmsLab\Graph\GraphGenerator;
use AlgorithmsLab\Graph\SplDijkstra;
use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Instrumentation\Measure;

return new Experiment(
    name: 'graph:dijkstra',
    description: 'Lazy heap, decrease-key heap, SplPriorityQueue and Bellman-Ford on one weighted graph.',
    supports: ['elements', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $vertices = $options->elements(500);
        $edgeCount = $options->capacity(2_000);
        $seed = $options->seed('dijkstra');
        $edges = GraphGenerator::random($vertices, $edgeCount, new DeterministicRandom($seed), false, 100.0);
        $graph = new AdjacencyList($vertices);

        foreach ($edges as $edge) {
            $graph->addEdge($edge->from, $edge->to, $edge->weight);
        }

        $output->heading(
            'Graph — Dijkstra variants',
            'All four implementations answer the same source query; only the heap strategy differs.',
        );
        $output->line(sprintf(
            '%-18s %12s %13s %10s %12s',
            'algorithm',
            'time',
            'relaxations',
            'heap pops',
            'node reads',
        ));
        $output->line(str_repeat('-', 72));

        $reference = null;

        foreach ([
            'lazy-heap' => static function () use ($graph): array {
                $counters = new Counters();
                $measure = new Measure();
                $measure->start();
                $result = Dijkstra::run($graph, 0, $counters);
                $measure->stop();

                return [$result, $measure->elapsedSeconds(), $counters];
            },
            'decrease-key' => static function () use ($graph): array {
                $counters = new Counters();
                $measure = new Measure();
                $measure->start();
                $result = DijkstraDecreaseKey::run($graph, 0, $counters);
                $measure->stop();

                return [$result, $measure->elapsedSeconds(), $counters];
            },
            'spl-priority' => static function () use ($graph): array {
                $counters = new Counters();
                $measure = new Measure();
                $measure->start();
                $result = SplDijkstra::run($graph, 0);
                $measure->stop();

                return [$result, $measure->elapsedSeconds(), $counters];
            },
            'bellman-ford' => static function () use ($graph): array {
                $counters = new Counters();
                $measure = new Measure();
                $measure->start();
                $result = BellmanFord::run($graph, 0);
                $measure->stop();

                return [$result, $measure->elapsedSeconds(), $counters];
            },
        ] as $name => $run) {
            [$result, $seconds, $counters] = $run();

            if ($reference === null) {
                $reference = $result;
            } elseif ($result->distances !== $reference->distances) {
                throw new LogicException('Shortest-path implementations disagree on distances');
            }

            $output->line(sprintf(
                '%-18s %10.3f ms %13d %10d %12d',
                $name,
                $seconds * 1000,
                $result->relaxations,
                $result->heapPops,
                $counters->nodeReads,
            ));
            $output->measure($name . '_seconds', $seconds);
            $output->measure($name . '_relaxations', $result->relaxations);
            $output->measure($name . '_heap_pops', $result->heapPops);
            $output->measure($name . '_node_reads', $counters->nodeReads);
        }

        $target = $vertices - 1;
        $targetDistance = $reference->distance($target);
        $output->line();
        $output->line(sprintf(
            'Distance to vertex %d: %s.',
            $target,
            $targetDistance === null ? 'unreachable' : number_format($targetDistance, 2),
        ));
        $output->line('Lazy Dijkstra may push several heap entries per vertex; decrease-key keeps at most V entries.');
        $output->line('Bellman-Ford has no heap but performs up to V−1 full edge passes; it is the price of accepting negative edges.');
        $output->line('SplPriorityQueue is a language baseline, not the reference; its internal comparisons are not counted here.');
        $output->line(sprintf('Vertices: %s. Edges: %s. Seed: %s.', number_format($vertices), number_format($edgeCount), $seed));

        $output->measure('vertices', $vertices);
        $output->measure('edges', $edgeCount);
        $output->measure('seed', $seed);
        $output->measure('target_distance', $targetDistance);
    },
);
