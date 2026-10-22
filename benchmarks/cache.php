<?php

declare(strict_types=1);

use AlgorithmsLab\Benchmark\Benchmark;
use AlgorithmsLab\Cache\ArrayLRU;
use AlgorithmsLab\Cache\HeapLFU;
use AlgorithmsLab\Cache\LFUCache;
use AlgorithmsLab\Cache\LRUCache;
use AlgorithmsLab\Cache\NaiveLRU;
use AlgorithmsLab\Foundation\DeterministicRandom;

$random = new DeterministicRandom('benchmark-cache');
$keys = [];

for ($i = 0; $i < 10_000; ++$i) {
    $keys[] = 'key-' . $random->nextInt();
}

$lru = new LRUCache(10_000);
$lfu = new LFUCache(10_000);
$heapLfu = new HeapLFU(10_000);
$arrayLru = new ArrayLRU(10_000);
$naiveLru = new NaiveLRU(10_000);
$plain = [];

foreach ($keys as $index => $key) {
    $lru->put($key, $index);
    $lfu->put($key, $index);
    $heapLfu->put($key, $index);
    $arrayLru->put($key, $index);
    $naiveLru->put($key, $index);
    $plain[$key] = $index;
}

$position = 0;
$nextKey = static function () use (&$position, $keys): string {
    $key = $keys[$position];
    $position = ($position + 1) % count($keys);

    return $key;
};

$lruEvict = new LRUCache(1_000);
$lfuEvict = new LFUCache(1_000);
$heapEvict = new HeapLFU(1_000);
$arrayEvict = new ArrayLRU(1_000);
$naiveEvict = new NaiveLRU(1_000);

foreach (array_slice($keys, 0, 1_000) as $index => $key) {
    $lruEvict->put($key, $index);
    $lfuEvict->put($key, $index);
    $heapEvict->put($key, $index);
    $arrayEvict->put($key, $index);
    $naiveEvict->put($key, $index);
}

$evictPosition = 0;
$nextEvictKey = static function () use (&$evictPosition, $keys): string {
    $key = 'new-' . $keys[$evictPosition];
    $evictPosition = ($evictPosition + 1) % count($keys);

    return $key;
};

return [
    new Benchmark('lru-get-hit', 100_000, static function () use ($lru, $nextKey): void {
        $lru->get($nextKey());
    }),
    new Benchmark('lfu-get-hit', 100_000, static function () use ($lfu, $nextKey): void {
        $lfu->get($nextKey());
    }),
    new Benchmark('heap-lfu-get-hit', 20_000, static function () use ($heapLfu, $nextKey): void {
        $heapLfu->get($nextKey());
    }),
    new Benchmark('array-lru-get-hit', 100_000, static function () use ($arrayLru, $nextKey): void {
        $arrayLru->get($nextKey());
    }),
    new Benchmark('naive-lru-get-hit', 1_000, static function () use ($naiveLru, $nextKey): void {
        $naiveLru->get($nextKey());
    }),
    new Benchmark('php-array-get', 100_000, static function () use ($plain, $nextKey): void {
        $plain[$nextKey()] ?? null;
    }),
    new Benchmark('lru-put-evict', 10_000, static function () use ($lruEvict, $nextEvictKey): void {
        $lruEvict->put($nextEvictKey(), 1);
    }),
    new Benchmark('lfu-put-evict', 10_000, static function () use ($lfuEvict, $nextEvictKey): void {
        $lfuEvict->put($nextEvictKey(), 1);
    }),
    new Benchmark('heap-lfu-put-evict', 10_000, static function () use ($heapEvict, $nextEvictKey): void {
        $heapEvict->put($nextEvictKey(), 1);
    }),
    new Benchmark('array-lru-put-evict', 10_000, static function () use ($arrayEvict, $nextEvictKey): void {
        $arrayEvict->put($nextEvictKey(), 1);
    }),
    new Benchmark('naive-put-evict', 100, static function () use ($naiveEvict, $nextEvictKey): void {
        $naiveEvict->put($nextEvictKey(), 1);
    }),
];
