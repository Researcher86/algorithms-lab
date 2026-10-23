<?php

declare(strict_types=1);

use AlgorithmsLab\ConsistentHash\HashRing;
use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;

return new Experiment(
    name: 'ring:balance',
    description: 'Distribution of keys across nodes as virtual-node count grows.',
    supports: ['elements', 'size'],
    run: static function (Options $options, Output $output): void {
        $keys = $options->elements(100_000);
        $nodeCount = max(2, $options->size(20));
        $keyList = [];

        for ($index = 0; $index < $keys; ++$index) {
            $keyList[] = 'customer-' . $index;
        }

        $output->heading(
            'Ring — virtual-node balance',
            'More virtual nodes make the ring less lumpy; they do not change the lookup algorithm.',
        );
        $output->line(sprintf('%-10s %10s %12s %10s %12s', 'vnodes', 'ring size', 'max/mean', 'CV', 'chi-square'));
        $output->line(str_repeat('-', 62));

        foreach ([1, 10, 100, 500] as $virtualNodes) {
            $ring = new HashRing($virtualNodes);

            for ($index = 0; $index < $nodeCount; ++$index) {
                $ring->addNode('node-' . $index);
            }

            /** @var array<string, int> $counts */
            $counts = array_fill_keys($ring->nodes(), 0);

            foreach ($keyList as $key) {
                $node = $ring->getNode($key);

                if ($node !== null) {
                    ++$counts[$node];
                }
            }

            $expected = $keys / $nodeCount;
            $maximum = $counts === [] ? 0 : max($counts);
            $mean = $keys / $nodeCount;
            $squared = 0.0;
            $chiSquare = 0.0;

            foreach ($counts as $count) {
                $squared += ($count - $mean) ** 2;
                $chiSquare += (($count - $expected) ** 2) / $expected;
            }

            $coefficient = sqrt($squared / $nodeCount) / $mean;
            $output->line(sprintf(
                '%-10d %10d %11.3f %10.3f %12.2f',
                $virtualNodes,
                $ring->virtualNodeCount(),
                $maximum / $mean,
                $coefficient,
                $chiSquare,
            ));
            $output->measure('vnodes_' . $virtualNodes . '_max_over_mean', $maximum / $mean);
            $output->measure('vnodes_' . $virtualNodes . '_coefficient_of_variation', $coefficient);
            $output->measure('vnodes_' . $virtualNodes . '_chi_square', $chiSquare);
        }

        $output->line();
        $output->line('max/mean = 1.0 is a perfectly even split; lower CV and chi-square are better.');
        $output->line('Chi-square is reported as a relative error score, not converted to a p-value.');
        $output->line(sprintf('Nodes: %d. Keys: %s.', $nodeCount, number_format($keys)));

        $output->measure('nodes', $nodeCount);
        $output->measure('keys', $keys);
    },
);
