<?php

declare(strict_types=1);

use AlgorithmsLab\Benchmark\Benchmark;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Stream\CountMinSketch;
use AlgorithmsLab\Stream\MisraGries;
use AlgorithmsLab\Stream\QuickSelect;
use AlgorithmsLab\Stream\SpaceSaving;
use AlgorithmsLab\Stream\StreamGenerator;
use AlgorithmsLab\Stream\TopKHeap;

$stream = StreamGenerator::zipf(50_000, 5_000, 1.1, new DeterministicRandom('benchmark-stream'));
$streamCount = count($stream);
$counts = [];

foreach ($stream as $key) {
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}

$topKPosition = 0;
$countMinPosition = 0;
$spaceSavingPosition = 0;
$misraGriesPosition = 0;
$topK = new TopKHeap(100);
$countMin = new CountMinSketch(1_024, 4);
$spaceSaving = new SpaceSaving(100);
$misraGries = new MisraGries(100);

return [
    new Benchmark('stream-topk-observe', 100_000, static function () use ($topK, $stream, $streamCount, &$topKPosition): void {
        $topK->observe($stream[$topKPosition++ % $streamCount]);
    }),
    new Benchmark('stream-topk-batch', 20, static function () use ($stream, $streamCount): void {
        $heap = new TopKHeap(100);

        for ($index = 0; $index < 10_000; ++$index) {
            $heap->observe($stream[$index % $streamCount]);
        }
    }),
    new Benchmark('stream-quickselect-1000', 100, static function () use ($counts): void {
        QuickSelect::top($counts, 1_000);
    }),
    new Benchmark('stream-count-min', 100_000, static function () use ($countMin, $stream, $streamCount, &$countMinPosition): void {
        $countMin->add($stream[$countMinPosition++ % $streamCount]);
    }),
    new Benchmark('stream-space-saving', 100_000, static function () use ($spaceSaving, $stream, $streamCount, &$spaceSavingPosition): void {
        $spaceSaving->observe($stream[$spaceSavingPosition++ % $streamCount]);
    }),
    new Benchmark('stream-misra-gries', 100_000, static function () use ($misraGries, $stream, $streamCount, &$misraGriesPosition): void {
        $misraGries->observe($stream[$misraGriesPosition++ % $streamCount]);
    }),
];
