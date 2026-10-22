<?php

declare(strict_types=1);

use AlgorithmsLab\Benchmark\Benchmark;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Foundation\Hashers;

$random = new DeterministicRandom('benchmark-demo');
$keys = [];

for ($i = 0; $i < 100; ++$i) {
    $keys[] = 'key-' . $random->nextInt();
}

return [
    new Benchmark('random-next', 10_000, static function () use ($random): void {
        $random->nextInt();
    }),
    new Benchmark('xxh128-100-keys', 1_000, static function () use ($keys): void {
        foreach ($keys as $key) {
            Hashers::xxh128($key);
        }
    }),
];
