<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Graph\AdjacencyList;
use AlgorithmsLab\Graph\Graph;
use AlgorithmsLab\Graph\GraphGenerator;
use AlgorithmsLab\Graph\TopologicalSort;
use AlgorithmsLab\Instrumentation\Measure;

return new Experiment(
    name: 'graph:topological-sort',
    description: 'Kahn and DFS topological orders on a random DAG, plus a cycle check.',
    supports: ['elements', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $vertices = $options->elements(1_000);
        $edgeCount = $options->capacity(4_000);
        $seed = $options->seed('topological');
        $edges = GraphGenerator::randomDag($vertices, $edgeCount, new DeterministicRandom($seed));
        $graph = new AdjacencyList($vertices, true);

        foreach ($edges as $edge) {
            $graph->addEdge($edge->from, $edge->to, $edge->weight);
        }

        $isValid = static function (Graph $candidate, array $order): bool {
            if (count($order) !== $candidate->vertexCount()) {
                return false;
            }

            $position = array_flip($order);

            foreach ($candidate->edges() as $edge) {
                if (($position[$edge->from] ?? -1) >= ($position[$edge->to] ?? -1)) {
                    return false;
                }
            }

            return true;
        };

        $output->heading(
            'Graph — topological sort',
            'A random DAG with edges pointing from lower to higher ids; both orders are checked, not compared for equality.',
        );
        $output->line(sprintf('%-12s %12s %10s %12s %12s', 'algorithm', 'time', 'order', 'valid', 'edge checks'));
        $output->line(str_repeat('-', 62));

        foreach ([
            'kahn' => static fn (): ?array => TopologicalSort::kahn($graph),
            'dfs' => static fn (): ?array => TopologicalSort::dfs($graph),
        ] as $name => $sort) {
            $measure = new Measure();
            $measure->start();
            $order = $sort();
            $measure->stop();
            $valid = $order !== null && $isValid($graph, $order);
            $length = $order === null ? 0 : count($order);
            $seconds = $measure->elapsedSeconds();

            $output->line(sprintf(
                '%-12s %10.3f ms %10d %12s %12d',
                $name,
                $seconds * 1000,
                $length,
                $valid ? 'yes' : 'no',
                count($edges),
            ));
            $output->measure($name . '_seconds', $seconds);
            $output->measure($name . '_length', $length);
            $output->measure($name . '_valid', $valid);
        }

        $cyclic = new AdjacencyList(3, true);
        $cyclic->addEdge(0, 1);
        $cyclic->addEdge(1, 2);
        $cyclic->addEdge(2, 0);
        $kahnCycle = TopologicalSort::kahn($cyclic);
        $dfsCycle = TopologicalSort::dfs($cyclic);

        $output->line();
        $output->line('The synthetic 0→1→2→0 graph is rejected by both algorithms.');
        $output->line(sprintf('Kahn result: %s. DFS result: %s.', $kahnCycle === null ? 'cycle' : 'order', $dfsCycle === null ? 'cycle' : 'order'));
        $output->line('Kahn is queue-driven and easy to explain; DFS carries colors and a recursion stack explicitly.');
        $output->line(sprintf('Vertices: %s. Edges: %s. Seed: %s.', number_format($vertices), number_format($edgeCount), $seed));

        $output->measure('vertices', $vertices);
        $output->measure('edges', $edgeCount);
        $output->measure('seed', $seed);
        $output->measure('cycle_kahn_detected', $kahnCycle === null);
        $output->measure('cycle_dfs_detected', $dfsCycle === null);
    },
);
