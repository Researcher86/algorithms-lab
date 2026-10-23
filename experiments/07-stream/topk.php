<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Instrumentation\Measure;
use AlgorithmsLab\Stream\QuickSelect;
use AlgorithmsLab\Stream\StreamGenerator;
use AlgorithmsLab\Stream\TopKHeap;

return new Experiment(
    name: 'stream:topk',
    description: 'Exact top-K by a streaming heap and by batch quickselect.',
    supports: ['elements', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(100_000);
        $items = min($elements, 10_000);
        $seed = $options->seed('stream-topk');
        $stream = StreamGenerator::zipf($elements, $items, 1.1, new DeterministicRandom($seed));
        $counts = [];

        foreach ($stream as $key) {
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $output->heading(
            'Stream — exact top-K',
            'The heap keeps K entries but still needs a count for every distinct key; quickselect needs the full map too.',
        );
        $output->line(sprintf('%-6s %10s %14s %14s %14s %14s %10s', 'K', 'distinct', 'heap update', 'heap memory', 'quickselect', 'quick memory', 'recall'));
        $output->line(str_repeat('-', 89));

        foreach ([10, 100, 1_000] as $k) {
            $expected = QuickSelect::top($counts, $k);
            $heapMeasure = new Measure();
            $heapMeasure->start();
            $heap = new TopKHeap($k);

            foreach ($stream as $key) {
                $heap->observe($key);
            }

            $heapMeasure->stop();
            $heapBytes = $heapMeasure->deltaBytes();
            $heapTop = $heap->top();
            $distinct = $heap->distinctCount();
            unset($heap);
            gc_collect_cycles();

            if ($heapTop !== $expected) {
                throw new LogicException('TopKHeap and QuickSelect disagree');
            }

            $quickMeasure = new Measure();
            $quickMeasure->start();
            $quick = QuickSelect::top($counts, $k);
            $quickMeasure->stop();
            $quickBytes = $quickMeasure->deltaBytes();

            if ($quick !== $expected) {
                throw new LogicException('QuickSelect did not return the expected top-K');
            }

            $wanted = array_column($expected, 'key');
            $found = array_column($heapTop, 'key');
            $recall = count(array_intersect($wanted, $found)) / max(1, count($wanted));

            $output->line(sprintf(
                '%-6d %10d %10.3f ms %11.1f KiB %10.3f ms %11.1f KiB %9.1f%%',
                $k,
                $distinct,
                $heapMeasure->elapsedSeconds() * 1000,
                $heapBytes / 1024,
                $quickMeasure->elapsedSeconds() * 1000,
                $quickBytes / 1024,
                $recall * 100,
            ));
            $output->measure('k_' . $k . '_heap_seconds', $heapMeasure->elapsedSeconds());
            $output->measure('k_' . $k . '_heap_bytes', $heapBytes);
            $output->measure('k_' . $k . '_quickselect_seconds', $quickMeasure->elapsedSeconds());
            $output->measure('k_' . $k . '_quickselect_bytes', $quickBytes);
            $output->measure('k_' . $k . '_recall', $recall);
        }

        $output->line();
        $output->line('Both methods are exact, so recall is 1.0; the lesson is the memory, not a ranking difference.');
        $output->line('The heap update is a stream pass; quickselect is a batch step over the already-built frequency map.');
        $output->line(sprintf('Elements: %s. Distinct keys: %s. Zipf alpha: 1.1. Seed: %s.', number_format($elements), number_format(count($counts)), $seed));

        $output->measure('elements', $elements);
        $output->measure('distinct', count($counts));
        $output->measure('seed', $seed);
    },
);
