<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\ByteFormatter;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Graph\AdjacencyList;
use AlgorithmsLab\Graph\AdjacencyMatrix;
use AlgorithmsLab\Graph\Bfs;
use AlgorithmsLab\Graph\CsrGraph;
use AlgorithmsLab\Graph\EdgeList;
use AlgorithmsLab\Graph\GraphGenerator;
use AlgorithmsLab\Instrumentation\Measure;

return new Experiment(
    name: 'graph:representations',
    description: 'Build cost, memory and BFS reach for four graph representations.',
    supports: ['elements', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $vertices = $options->elements(1_000);
        $edgeCount = $options->capacity(4_000);
        $seed = $options->seed('representations');
        $edges = GraphGenerator::random($vertices, $edgeCount, new DeterministicRandom($seed), false, 10.0);

        $output->heading(
            'Graph — representations',
            'The same undirected weighted edge list is loaded into each representation, then BFS runs from vertex 0.',
        );
        $output->line(sprintf('%-16s %14s %14s %12s %10s', 'representation', 'PHP memory', 'bytes/vertex', 'build', 'BFS seen'));
        $output->line(str_repeat('-', 72));

        $measure = new Measure();
        $measure->start();
        $list = new AdjacencyList($vertices);

        foreach ($edges as $edge) {
            $list->addEdge($edge->from, $edge->to, $edge->weight);
        }

        $measure->stop();
        $listBytes = $measure->deltaBytes();
        $listBuild = $measure->elapsedSeconds();
        $listSeen = count(Bfs::run($list, 0)->order);
        $output->line(sprintf(
            '%-16s %14s %14.1f %11.3f ms %10d',
            'AdjacencyList',
            ByteFormatter::format($listBytes),
            $listBytes / $vertices,
            $listBuild * 1000,
            $listSeen,
        ));
        $output->measure('adjacency_list_bytes', $listBytes);
        $output->measure('adjacency_list_bytes_per_vertex', $listBytes / $vertices);
        $output->measure('adjacency_list_build_seconds', $listBuild);
        unset($list);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $matrix = new AdjacencyMatrix($vertices);

        foreach ($edges as $edge) {
            $matrix->addEdge($edge->from, $edge->to, $edge->weight);
        }

        $measure->stop();
        $matrixBytes = $measure->deltaBytes();
        $matrixBuild = $measure->elapsedSeconds();
        $matrixSeen = count(Bfs::run($matrix, 0)->order);
        $output->line(sprintf(
            '%-16s %14s %14.1f %11.3f ms %10d',
            'AdjacencyMatrix',
            ByteFormatter::format($matrixBytes),
            $matrixBytes / $vertices,
            $matrixBuild * 1000,
            $matrixSeen,
        ));
        $output->measure('adjacency_matrix_bytes', $matrixBytes);
        $output->measure('adjacency_matrix_bytes_per_vertex', $matrixBytes / $vertices);
        $output->measure('adjacency_matrix_build_seconds', $matrixBuild);
        unset($matrix);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $edgeList = new EdgeList($vertices);

        foreach ($edges as $edge) {
            $edgeList->addEdge($edge->from, $edge->to, $edge->weight);
        }

        $measure->stop();
        $edgeBytes = $measure->deltaBytes();
        $edgeBuild = $measure->elapsedSeconds();
        $edgeSeen = count(Bfs::run($edgeList, 0)->order);
        $output->line(sprintf(
            '%-16s %14s %14.1f %11.3f ms %10d',
            'EdgeList',
            ByteFormatter::format($edgeBytes),
            $edgeBytes / $vertices,
            $edgeBuild * 1000,
            $edgeSeen,
        ));
        $output->measure('edge_list_bytes', $edgeBytes);
        $output->measure('edge_list_bytes_per_vertex', $edgeBytes / $vertices);
        $output->measure('edge_list_build_seconds', $edgeBuild);
        unset($edgeList);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $csr = CsrGraph::fromEdges($vertices, $edges);

        $measure->stop();
        $csrBytes = $measure->deltaBytes();
        $csrBuild = $measure->elapsedSeconds();
        $csrSeen = count(Bfs::run($csr, 0)->order);
        $output->line(sprintf(
            '%-16s %14s %14.1f %11.3f ms %10d',
            'CsrGraph',
            ByteFormatter::format($csrBytes),
            $csrBytes / $vertices,
            $csrBuild * 1000,
            $csrSeen,
        ));
        $output->measure('csr_bytes', $csrBytes);
        $output->measure('csr_bytes_per_vertex', $csrBytes / $vertices);
        $output->measure('csr_build_seconds', $csrBuild);

        $output->line();
        $output->line('BFS seen is the number of reached vertices; a random graph can contain isolated vertices.');
        $output->line('AdjacencyMatrix is dense by construction: its memory is V² regardless of E.');
        $output->line('EdgeList build is O(E²) because addEdge keeps the graph simple by scanning for an existing pair.');
        $output->line(sprintf(
            'Vertices: %s. Edges: %s. Seed: %s.',
            number_format($vertices),
            number_format($edgeCount),
            $seed,
        ));

        $output->measure('vertices', $vertices);
        $output->measure('edges', $edgeCount);
        $output->measure('seed', $seed);
        $output->measure('bfs_seen', $listSeen);
        $output->measure('representations_compared', 4);
    },
);
