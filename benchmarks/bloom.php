<?php

declare(strict_types=1);

use AlgorithmsLab\Benchmark\Benchmark;
use AlgorithmsLab\BloomFilter\BloomFilter;
use AlgorithmsLab\Foundation\DeterministicRandom;

$random = new DeterministicRandom('benchmark-bloom');
$present = [];
$absent = [];

for ($i = 0; $i < 10_000; ++$i) {
    $present[] = 'present-' . $random->nextInt();
    $absent[] = 'absent-' . $random->nextInt();
}

$filter = new BloomFilter(10_000, 0.01);

foreach ($present as $key) {
    $filter->add($key);
}

$array = [];

foreach ($present as $index => $key) {
    $array[$key] = $index;
}

$presentPosition = 0;
$nextPresent = static function () use (&$presentPosition, $present): string {
    $key = $present[$presentPosition];
    $presentPosition = ($presentPosition + 1) % count($present);

    return $key;
};

$absentPosition = 0;
$nextAbsent = static function () use (&$absentPosition, $absent): string {
    $key = $absent[$absentPosition];
    $absentPosition = ($absentPosition + 1) % count($absent);

    return $key;
};

return [
    new Benchmark('bloom-add', 100_000, static function () use ($filter, $nextPresent): void {
        $filter->add($nextPresent());
    }),
    new Benchmark('bloom-check-hit', 100_000, static function () use ($filter, $nextPresent): void {
        $filter->mightContain($nextPresent());
    }),
    new Benchmark('bloom-check-miss', 100_000, static function () use ($filter, $nextAbsent): void {
        $filter->mightContain($nextAbsent());
    }),
    new Benchmark('php-array-check', 100_000, static function () use ($array, $nextPresent): void {
        $array[$nextPresent()] ?? null;
    }),
];
