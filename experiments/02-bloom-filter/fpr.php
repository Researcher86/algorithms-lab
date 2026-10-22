<?php

declare(strict_types=1);

use AlgorithmsLab\BloomFilter\BloomFilter;
use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;

return new Experiment(
    name: 'bloom:fpr',
    description: 'Measured false-positive rate against the requested rate.',
    supports: ['elements', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(10_000);
        $seed = $options->seed('bloom-fpr');
        $present = [];
        $absent = [];

        for ($i = 0; $i < $elements; ++$i) {
            $present[] = sprintf('present-%s-%d', $seed, $i);
            $absent[] = sprintf('absent-%s-%d', $seed, $i);
        }

        $output->heading(
            'Bloom filter — false positives',
            'The filter is sized for n items and a target p; the measured rate comes from n absent queries.',
        );
        $output->line(sprintf(
            '%-8s %10s %10s %8s %8s %12s %10s',
            'target',
            'measured',
            'estimated',
            'bits/key',
            'hashes',
            'bit density',
            'bits',
        ));
        $output->line(str_repeat('-', 76));

        foreach ([0.01, 0.05, 0.10] as $target) {
            $filter = new BloomFilter($elements, $target);

            foreach ($present as $key) {
                $filter->add($key);
            }

            $filter->counters()->reset();
            $falsePositives = 0;

            foreach ($absent as $key) {
                if ($filter->mightContain($key)) {
                    ++$falsePositives;
                }
            }

            $measured = $falsePositives / $elements;
            $label = sprintf('p%02d', (int) round($target * 100));

            $output->line(sprintf(
                '%-8.3f %10.4f %10.4f %8.2f %8d %12.3f %10s',
                $target,
                $measured,
                $filter->estimatedFalsePositiveRate(),
                $filter->bitCount() / $elements,
                $filter->hashCount(),
                $filter->bitDensity(),
                number_format($filter->bitCount()),
            ));

            $output->measure($label . '_target', $target);
            $output->measure($label . '_measured', $measured);
            $output->measure($label . '_estimated', $filter->estimatedFalsePositiveRate());
            $output->measure($label . '_bits_per_key', $filter->bitCount() / $elements);
            $output->measure($label . '_hashes', $filter->hashCount());
            $output->measure($label . '_bit_density', $filter->bitDensity());
        }

        $output->line();
        $output->line('Measured FPR is one deterministic sample of n absent keys, not a confidence interval.');
        $output->line('A Bloom filter never has false negatives after an add; it cannot enumerate or delete keys.');
        $output->line(sprintf('Seed: %s. Elements: %s.', $seed, number_format($elements)));

        $output->measure('elements', $elements);
        $output->measure('seed', $seed);
    },
);
