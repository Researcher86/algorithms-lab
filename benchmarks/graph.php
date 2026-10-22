<?php

declare(strict_types=1);

use AlgorithmsLab\Benchmark\Benchmark;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Graph\AdjacencyList;
use AlgorithmsLab\Graph\BellmanFord;
use AlgorithmsLab\Graph\Bfs;
use AlgorithmsLab\Graph\Dfs;
use AlgorithmsLab\Graph\Dijkstra;
use AlgorithmsLab\Graph\DijkstraDecreaseKey;
use AlgorithmsLab\Graph\GraphGenerator;
use AlgorithmsLab\Graph\Kruskal;
use AlgorithmsLab\Graph\Prim;
use AlgorithmsLab\Graph\SplDijkstra;
use AlgorithmsLab\Graph\TopologicalSort;

$random = new DeterministicRandom('benchmark-graph');
$sparseEdges = GraphGenerator::random(1_000, 4_000, $random);
$denseEdges = GraphGenerator::random(1_000, 16_000, new DeterministicRandom('benchmark-graph-dense'));
$weightedEdges = GraphGenerator::random(500, 2_000, new DeterministicRandom('benchmark-graph-weighted'), false, 100.0);
$dagEdges = GraphGenerator::randomDag(1_000, 4_000, new DeterministicRandom('benchmark-graph-dag'));

$sparse = new AdjacencyList(1_000);
$dense = new AdjacencyList(1_000);
$weighted = new AdjacencyList(500);
$dag = new AdjacencyList(1_000, true);

foreach ($sparseEdges as $edge) {
    $sparse->addEdge($edge->from, $edge->to, $edge->weight);
}

foreach ($denseEdges as $edge) {
    $dense->addEdge($edge->from, $edge->to, $edge->weight);
}

foreach ($weightedEdges as $edge) {
    $weighted->addEdge($edge->from, $edge->to, $edge->weight);
}

foreach ($dagEdges as $edge) {
    $dag->addEdge($edge->from, $edge->to, $edge->weight);
}

return [
    new Benchmark('bfs-sparse', 200, static function () use ($sparse): void {
        Bfs::run($sparse, 0);
    }),
    new Benchmark('dfs-sparse', 200, static function () use ($sparse): void {
        Dfs::run($sparse, 0);
    }),
    new Benchmark('bfs-dense', 100, static function () use ($dense): void {
        Bfs::run($dense, 0);
    }),
    new Benchmark('dfs-dense', 100, static function () use ($dense): void {
        Dfs::run($dense, 0);
    }),
    new Benchmark('dijkstra-lazy', 50, static function () use ($weighted): void {
        Dijkstra::run($weighted, 0);
    }),
    new Benchmark('dijkstra-decrease-key', 50, static function () use ($weighted): void {
        DijkstraDecreaseKey::run($weighted, 0);
    }),
    new Benchmark('dijkstra-spl', 50, static function () use ($weighted): void {
        SplDijkstra::run($weighted, 0);
    }),
    new Benchmark('bellman-ford', 5, static function () use ($weighted): void {
        BellmanFord::run($weighted, 0);
    }),
    new Benchmark('topo-kahn', 200, static function () use ($dag): void {
        TopologicalSort::kahn($dag);
    }),
    new Benchmark('topo-dfs', 200, static function () use ($dag): void {
        TopologicalSort::dfs($dag);
    }),
    new Benchmark('kruskal', 50, static function () use ($weighted): void {
        Kruskal::run($weighted);
    }),
    new Benchmark('prim', 50, static function () use ($weighted): void {
        Prim::run($weighted, 0);
    }),
];
