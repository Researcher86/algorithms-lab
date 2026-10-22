<?php

declare(strict_types=1);

use AlgorithmsLab\Cache\ArrayLRU;
use AlgorithmsLab\Cache\HeapLFU;
use AlgorithmsLab\Cache\LFUCache;
use AlgorithmsLab\Cache\LRUCache;
use AlgorithmsLab\Cache\NaiveLRU;
use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\ByteFormatter;
use AlgorithmsLab\Instrumentation\Measure;

return new Experiment(
    name: 'cache:memory',
    description: 'PHP-allocator cost per entry for four cache policies.',
    supports: ['elements', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(10_000);
        $seed = $options->seed('cache-memory');
        $keys = [];

        for ($i = 0; $i < $elements; ++$i) {
            $keys[] = sprintf('cache-%s-%d', $seed, $i);
        }

        $output->heading(
            'Cache — memory',
            'The same keys and integer values are inserted into each policy at a capacity equal to the item count.',
        );
        $output->line('PHP allocator only; this is not RSS and not a prediction for another language.');
        $output->line();
        $output->line(sprintf('%-14s %14s %14s', 'policy', 'PHP memory', 'bytes/entry'));
        $output->line(str_repeat('-', 46));

        $measure = new Measure();
        $measure->start();
        $lru = new LRUCache($elements);

        foreach ($keys as $index => $key) {
            $lru->put($key, $index);
        }

        $measure->stop();
        $lruBytes = $measure->deltaBytes();
        $output->line(sprintf('%-14s %14s %14.1f', 'LRU', ByteFormatter::format($lruBytes), $lruBytes / $elements));
        $output->measure('lru_bytes', $lruBytes);
        $output->measure('lru_bytes_per_entry', $lruBytes / $elements);
        unset($lru);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $lfu = new LFUCache($elements);

        foreach ($keys as $index => $key) {
            $lfu->put($key, $index);
        }

        $measure->stop();
        $lfuBytes = $measure->deltaBytes();
        $output->line(sprintf('%-14s %14s %14.1f', 'LFU', ByteFormatter::format($lfuBytes), $lfuBytes / $elements));
        $output->measure('lfu_bytes', $lfuBytes);
        $output->measure('lfu_bytes_per_entry', $lfuBytes / $elements);
        unset($lfu);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $heapLfu = new HeapLFU($elements);

        foreach ($keys as $index => $key) {
            $heapLfu->put($key, $index);
        }

        $measure->stop();
        $heapBytes = $measure->deltaBytes();
        $output->line(sprintf('%-14s %14s %14.1f', 'HeapLFU', ByteFormatter::format($heapBytes), $heapBytes / $elements));
        $output->measure('heap_lfu_bytes', $heapBytes);
        $output->measure('heap_lfu_bytes_per_entry', $heapBytes / $elements);
        unset($heapLfu);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $array = new ArrayLRU($elements);

        foreach ($keys as $index => $key) {
            $array->put($key, $index);
        }

        $measure->stop();
        $arrayBytes = $measure->deltaBytes();
        $output->line(sprintf('%-14s %14s %14.1f', 'ArrayLRU', ByteFormatter::format($arrayBytes), $arrayBytes / $elements));
        $output->measure('array_lru_bytes', $arrayBytes);
        $output->measure('array_lru_bytes_per_entry', $arrayBytes / $elements);
        unset($array);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $naive = new NaiveLRU($elements);

        foreach ($keys as $index => $key) {
            $naive->put($key, $index);
        }

        $measure->stop();
        $naiveBytes = $measure->deltaBytes();
        $output->line(sprintf('%-14s %14s %14.1f', 'NaiveLRU', ByteFormatter::format($naiveBytes), $naiveBytes / $elements));
        $output->measure('naive_lru_bytes', $naiveBytes);
        $output->measure('naive_lru_bytes_per_entry', $naiveBytes / $elements);
        unset($naive);
        gc_collect_cycles();

        $measure = new Measure();
        $measure->start();
        $plain = [];

        foreach ($keys as $index => $key) {
            $plain[$key] = $index;
        }

        $measure->stop();
        $plainBytes = $measure->deltaBytes();
        $output->line(sprintf('%-14s %14s %14.1f', 'PHP array', ByteFormatter::format($plainBytes), $plainBytes / $elements));
        $output->measure('php_array_bytes', $plainBytes);
        $output->measure('php_array_bytes_per_entry', $plainBytes / $elements);

        $output->line();
        $output->line('LRU and LFU each pay for a map plus one node object per entry.');
        $output->line('HeapLFU also keeps one heap record per access; its memory grows with the access count.');
        $output->line('ArrayLRU gets the order from the PHP array and is the cheapest of the cache policies here.');
        $output->line('NaiveLRU pays for a PHP array per entry; it is the baseline for a list of pairs, not a recommendation.');
        $output->line(sprintf('Seed: %s. Entries: %s.', $seed, number_format($elements)));

        $output->measure('elements', $elements);
        $output->measure('seed', $seed);
    },
);
