<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Instrumentation\Measure;

return new Experiment(
    name: 'demo:smoke',
    description: 'Checks the experiment harness end to end on a seeded dataset.',
    supports: ['elements', 'iterations', 'seed'],
    run: static function (Options $options, Output $output): void {
        $elements = $options->elements(10_000);
        $iterations = $options->iterations(3);
        $seed = $options->seed('demo');

        $output->heading('demo:smoke', 'A seeded checksum, measured once.');
        $output->line(sprintf(
            'Generating %s values per run, %d run(s), seed "%s".',
            number_format($elements),
            $iterations,
            $seed,
        ));
        $output->line();

        $measure = new Measure();
        $measure->start();
        $checksum = 0;

        for ($run = 0; $run < $iterations; ++$run) {
            $random = new DeterministicRandom($seed . ':' . $run);

            for ($i = 0; $i < $elements; ++$i) {
                $checksum ^= $random->nextInt();
            }
        }

        $measure->stop();
        $output->line(sprintf('Checksum:       %d', $checksum));
        $output->line(sprintf('Elapsed:        %.6f s', $measure->elapsedSeconds()));
        $output->line(sprintf('PHP memory:     %+d bytes', $measure->deltaBytes()));

        $output->measure('elements', $elements);
        $output->measure('iterations', $iterations);
        $output->measure('seed', $seed);
        $output->measure('checksum', $checksum);
        $output->measure('elapsed_seconds', $measure->elapsedSeconds());
        $output->measure('php_delta_bytes', $measure->deltaBytes());
    },
);
