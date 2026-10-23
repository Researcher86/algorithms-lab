<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Instrumentation\Measure;
use AlgorithmsLab\Stream\CountMinSketch;
use AlgorithmsLab\Stream\MisraGries;
use AlgorithmsLab\Stream\SpaceSaving;
use AlgorithmsLab\Stream\StreamGenerator;
use AlgorithmsLab\Stream\TopKHeap;

return new Experiment(
    name: 'stream:all-unique',
    description: 'Exact counting versus fixed-size summaries when every stream item is new.',
    supports: ['elements'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(100_000);
        $stream = StreamGenerator::allUnique($elements);
        $k = 100;

        $output->heading(
            'Stream — all unique',
            'Every item has frequency 1. The exact heap still stores every key; the summaries keep a fixed number of counters.',
        );
        $output->line(sprintf('%-20s %12s %14s %12s %14s', 'structure', 'logical size', 'PHP memory', 'bytes/unit', 'sample'));
        $output->line(str_repeat('-', 78));

        $heapMeasure = new Measure();
        $heapMeasure->start();
        $heap = new TopKHeap($k);

        foreach ($stream as $key) {
            $heap->observe($key);
        }

        $heapMeasure->stop();
        $heapTopCount = $heap->top()[0]['count'] ?? 0;
        $distinct = $heap->distinctCount();
        $heapBytes = $heapMeasure->deltaBytes();
        $output->line(sprintf(
            '%-20s %12d %11.1f KiB %12.2f %14d',
            'TopKHeap (exact)',
            $distinct,
            $heapBytes / 1024,
            $heapBytes / max(1, $elements),
            $heapTopCount,
        ));
        $output->measure('exact_distinct', $distinct);
        $output->measure('exact_bytes', $heapBytes);
        $output->measure('exact_bytes_per_item', $heapBytes / max(1, $elements));
        unset($heap);
        gc_collect_cycles();

        $sketchMeasure = new Measure();
        $sketchMeasure->start();
        $sketch = new CountMinSketch(2_048, 4);

        foreach ($stream as $key) {
            $sketch->add($key);
        }

        $sketchMeasure->stop();
        $sketchBytes = $sketchMeasure->deltaBytes();
        $output->line(sprintf(
            '%-20s %12d %11.1f KiB %12.2f %14d',
            'CountMinSketch',
            $sketch->cells(),
            $sketchBytes / 1024,
            $sketchBytes / max(1, $sketch->cells()),
            $sketch->estimate($stream[0] ?? ''),
        ));
        $output->measure('count_min_cells', $sketch->cells());
        $output->measure('count_min_bytes', $sketchBytes);
        $output->measure('count_min_bytes_per_cell', $sketchBytes / max(1, $sketch->cells()));
        unset($sketch);
        gc_collect_cycles();

        foreach ([
            'SpaceSaving' => static function () use ($k): SpaceSaving {
                return new SpaceSaving($k);
            },
            'MisraGries' => static function () use ($k): MisraGries {
                return new MisraGries($k);
            },
        ] as $name => $factory) {
            $measure = new Measure();
            $measure->start();
            $summary = $factory();

            foreach ($stream as $key) {
                $summary->observe($key);
            }

            $measure->stop();
            $bytes = $measure->deltaBytes();
            $topCount = $summary->top(1)[0]['count'] ?? 0;
            $output->line(sprintf(
                '%-20s %12d %11.1f KiB %12.2f %14d',
                $name,
                $summary->size(),
                $bytes / 1024,
                $bytes / max(1, $summary->capacity()),
                $topCount,
            ));
            $output->measure(strtolower($name) . '_entries', $summary->size());
            $output->measure(strtolower($name) . '_bytes', $bytes);
            $output->measure(strtolower($name) . '_top_count', $topCount);

            unset($summary);
            gc_collect_cycles();
        }

        $output->line();
        $output->line('The exact heap grows with the number of distinct keys; the summaries do not, but their counters are estimates.');
        $output->line('Bytes per unit means per stream item for the heap, per cell for Count-Min, and per configured slot for summaries.');
        $output->line('"Sample" is the exact top count, the sketch estimate for the first item, or the summary\'s largest counter.');
        $output->line('With all frequencies equal, Space-Saving and Misra-Gries cannot distinguish a real winner: the stream has none.');
        $output->line(sprintf('Items: %s. K: %d. Count-Min width: 2,048, depth: 4.', number_format($elements), $k));

        $output->measure('elements', $elements);
        $output->measure('k', $k);
    },
);
