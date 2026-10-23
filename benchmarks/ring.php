<?php

declare(strict_types=1);

use AlgorithmsLab\Benchmark\Benchmark;
use AlgorithmsLab\ConsistentHash\HashRing;
use AlgorithmsLab\ConsistentHash\ModuloHash;
use AlgorithmsLab\ConsistentHash\RendezvousHash;
use AlgorithmsLab\Foundation\DeterministicRandom;

$nodeCount = 20;
$virtualNodes = 100;
$keys = [];

for ($index = 0; $index < 10_000; ++$index) {
    $keys[] = 'key-' . $index;
}

$random = new DeterministicRandom('benchmark-ring-queries');
$queries = [];

for ($index = 0; $index < 50_000; ++$index) {
    $queries[] = $keys[$random->intBetween(0, count($keys) - 1)];
}

$queryCount = count($queries);
$ring = new HashRing($virtualNodes);
$modulo = new ModuloHash();
$rendezvous = new RendezvousHash();

for ($index = 0; $index < $nodeCount; ++$index) {
    $node = 'node-' . $index;
    $ring->addNode($node);
    $modulo->addNode($node);
    $rendezvous->addNode($node);
}

$ringPosition = 0;
$moduloPosition = 0;
$rendezvousPosition = 0;
$replicaPosition = 0;

return [
    new Benchmark('ring-lookup', 100_000, static function () use ($ring, $queries, $queryCount, &$ringPosition): void {
        $ring->getNode($queries[$ringPosition++ % $queryCount]);
    }),
    new Benchmark('ring-replicas-3', 50_000, static function () use ($ring, $queries, $queryCount, &$replicaPosition): void {
        $ring->getNodes($queries[$replicaPosition++ % $queryCount], 3);
    }),
    new Benchmark('modulo-lookup', 100_000, static function () use ($modulo, $queries, $queryCount, &$moduloPosition): void {
        $modulo->getNode($queries[$moduloPosition++ % $queryCount]);
    }),
    new Benchmark('rendezvous-lookup', 50_000, static function () use ($rendezvous, $queries, $queryCount, &$rendezvousPosition): void {
        $rendezvous->getNode($queries[$rendezvousPosition++ % $queryCount]);
    }),
    new Benchmark('ring-add-remove', 1_000, static function () use ($ring): void {
        $ring->addNode('temporary-node');
        $ring->removeNode('temporary-node');
    }),
    new Benchmark('modulo-add-remove', 1_000, static function () use ($modulo): void {
        $modulo->addNode('temporary-node');
        $modulo->removeNode('temporary-node');
    }),
    new Benchmark('rendezvous-add-remove', 1_000, static function () use ($rendezvous): void {
        $rendezvous->addNode('temporary-node');
        $rendezvous->removeNode('temporary-node');
    }),
];
