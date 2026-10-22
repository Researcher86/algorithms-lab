<?php

declare(strict_types=1);

use AlgorithmsLab\Benchmark\Benchmark;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\HashTable\ChainingHashTable;
use AlgorithmsLab\HashTable\OpenAddressingHashTable;
use AlgorithmsLab\HashTable\ProbeStrategy;

$random = new DeterministicRandom('benchmark-hash-table');
$keys = [];

for ($i = 0; $i < 10_000; ++$i) {
    $keys[] = 'key-' . $random->nextInt();
}

$insertKeys = array_slice($keys, 0, 1_000);
$chaining = new ChainingHashTable(
    capacity: 16_384,
    maxLoadFactor: 1.0,
    autoResize: false,
);

foreach ($keys as $index => $key) {
    $chaining->put($key, $index);
}

$openTables = [];

foreach (ProbeStrategy::cases() as $strategy) {
    $table = new OpenAddressingHashTable(
        capacity: 16_384,
        strategy: $strategy,
        maxLoadFactor: 1.0,
    );

    foreach ($keys as $index => $key) {
        $table->put($key, $index);
    }

    $openTables[$strategy->value] = $table;
}

$array = [];

foreach ($keys as $index => $key) {
    $array[$key] = $index;
}

$position = 0;
$nextKey = static function () use (&$position, $keys): string {
    $key = $keys[$position];
    $position = ($position + 1) % count($keys);

    return $key;
};

return [
    new Benchmark('chaining-get', 100_000, static function () use ($chaining, $nextKey): void {
        $chaining->get($nextKey());
    }),
    new Benchmark('open-linear-get', 100_000, static function () use ($openTables, $nextKey): void {
        $openTables['linear']->get($nextKey());
    }),
    new Benchmark('open-quadratic-get', 100_000, static function () use ($openTables, $nextKey): void {
        $openTables['quadratic']->get($nextKey());
    }),
    new Benchmark('open-double-get', 100_000, static function () use ($openTables, $nextKey): void {
        $openTables['double']->get($nextKey());
    }),
    new Benchmark('php-array-get', 100_000, static function () use ($array, $nextKey): void {
        $array[$nextKey()] ?? null;
    }),
    new Benchmark('chaining-insert-1k', 10, static function () use ($insertKeys): void {
        $table = new ChainingHashTable(
            capacity: 2_048,
            maxLoadFactor: 1.0,
            autoResize: false,
        );

        foreach ($insertKeys as $index => $key) {
            $table->put($key, $index);
        }
    }),
    new Benchmark('open-linear-insert-1k', 10, static function () use ($insertKeys): void {
        $table = new OpenAddressingHashTable(
            capacity: 2_048,
            maxLoadFactor: 1.0,
        );

        foreach ($insertKeys as $index => $key) {
            $table->put($key, $index);
        }
    }),
];
