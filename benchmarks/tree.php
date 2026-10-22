<?php

declare(strict_types=1);

use AlgorithmsLab\Benchmark\Benchmark;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Tree\BPlusTree;
use AlgorithmsLab\Tree\BTree;
use AlgorithmsLab\Tree\KeyGenerator;
use AlgorithmsLab\Tree\PhpArrayIndex;
use AlgorithmsLab\Tree\SortedArrayIndex;

$elements = 10_000;
$keys = KeyGenerator::shuffled($elements, 'benchmark-tree');
$ascending = KeyGenerator::ascending($elements);
$random = new DeterministicRandom('benchmark-tree-queries');
$queries = [];

for ($index = 0; $index < 20_000; ++$index) {
    $queries[] = $random->intBetween(0, $elements - 1);
}

$queryCount = count($queries);
$btree = new BTree(8);
$bplus = new BPlusTree(8);

foreach ($keys as $key) {
    $btree->insert($key);
    $bplus->insert($key);
}

$sorted = new SortedArrayIndex();

foreach ($ascending as $key) {
    $sorted->insert($key);
}

$phpArray = new PhpArrayIndex();

foreach ($ascending as $key) {
    $phpArray->insert($key);
}

$insertKeys = array_slice($keys, 0, 1_000);
$deleteKeys = array_slice($keys, 0, 1_000);
$deleteOnly = array_slice($keys, 0, 200);
$btreePosition = 0;
$bplusPosition = 0;
$sortedPosition = 0;
$phpPosition = 0;
$rangePosition = 0;

return [
    new Benchmark('btree-search', 100_000, static function () use ($btree, $queries, $queryCount, &$btreePosition): void {
        $btree->search($queries[$btreePosition++ % $queryCount]);
    }),
    new Benchmark('bplus-search', 100_000, static function () use ($bplus, $queries, $queryCount, &$bplusPosition): void {
        $bplus->search($queries[$bplusPosition++ % $queryCount]);
    }),
    new Benchmark('sorted-array-search', 100_000, static function () use ($sorted, $queries, $queryCount, &$sortedPosition): void {
        $sorted->search($queries[$sortedPosition++ % $queryCount]);
    }),
    new Benchmark('php-array-search', 100_000, static function () use ($phpArray, $queries, $queryCount, &$phpPosition): void {
        $phpArray->search($queries[$phpPosition++ % $queryCount]);
    }),
    new Benchmark('btree-insert-1k', 10, static function () use ($insertKeys): void {
        $tree = new BTree(8);

        foreach ($insertKeys as $key) {
            $tree->insert($key);
        }
    }),
    new Benchmark('bplus-insert-1k', 10, static function () use ($insertKeys): void {
        $tree = new BPlusTree(8);

        foreach ($insertKeys as $key) {
            $tree->insert($key);
        }
    }),
    new Benchmark('bplus-bulk-load-10k', 10, static function () use ($ascending): void {
        BPlusTree::bulkLoad(8, $ascending);
    }),
    new Benchmark('btree-build-delete-200', 10, static function () use ($deleteKeys, $deleteOnly): void {
        $tree = new BTree(8);

        foreach ($deleteKeys as $key) {
            $tree->insert($key);
        }

        foreach ($deleteOnly as $key) {
            $tree->delete($key);
        }
    }),
    new Benchmark('bplus-range-100', 100_000, static function () use ($bplus, &$rangePosition): void {
        $start = $rangePosition++ % 9_900;
        $bplus->range($start, $start + 99);
    }),
];
