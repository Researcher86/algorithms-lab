<?php

declare(strict_types=1);

use AlgorithmsLab\ConsistentHash\HashRing;
use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;

return new Experiment(
    name: 'ring:replicas',
    description: 'Replica selection and fallback behavior when the primary node fails.',
    supports: ['elements', 'size', 'capacity'],
    run: static function (Options $options, Output $output): void {
        $keys = $options->elements(20_000);
        $nodeCount = max(3, $options->size(10));
        $virtualNodes = max(1, $options->capacity(100));
        $keyList = [];

        for ($index = 0; $index < $keys; ++$index) {
            $keyList[] = 'item-' . $index;
        }

        $ring = new HashRing($virtualNodes);

        for ($index = 0; $index < $nodeCount; ++$index) {
            $ring->addNode('node-' . $index);
        }

        $output->heading(
            'Ring — replicas and failure',
            'The first clockwise owner is the primary; the next distinct owners are its fallbacks.',
        );
        $output->line(sprintf('%-4s %14s %16s', 'R', 'avg owners', 'node coverage'));
        $output->line(str_repeat('-', 38));

        foreach ([1, 2, 3] as $replicas) {
            $owners = 0;
            $seenNodes = [];

            foreach ($keyList as $key) {
                $nodes = $ring->getNodes($key, $replicas);
                $owners += count($nodes);

                foreach ($nodes as $node) {
                    $seenNodes[$node] = true;
                }
            }

            $output->line(sprintf(
                '%-4d %14.2f %12d/%d',
                $replicas,
                $owners / $keys,
                count($seenNodes),
                $nodeCount,
            ));
            $output->measure('replicas_' . $replicas . '_average_owners', $owners / $keys);
            $output->measure('replicas_' . $replicas . '_node_coverage', count($seenNodes));
        }

        $primary = [];
        $fallback = [];

        foreach ($keyList as $key) {
            $nodes = $ring->getNodes($key, 2);
            $primary[$key] = $nodes[0];
            $fallback[$key] = $nodes[1];
        }

        $failedNode = 'node-2';
        $ring->removeNode($failedNode);
        $affected = 0;
        $fallbackMatches = 0;
        $unaffected = 0;
        $unaffectedMatches = 0;

        foreach ($keyList as $key) {
            $newPrimary = $ring->getNode($key);

            if ($primary[$key] === $failedNode) {
                ++$affected;

                if ($newPrimary === $fallback[$key]) {
                    ++$fallbackMatches;
                }
            } else {
                ++$unaffected;

                if ($newPrimary === $primary[$key]) {
                    ++$unaffectedMatches;
                }
            }
        }

        $output->line();
        $output->line(sprintf('Removed %s:', $failedNode));
        $output->line(sprintf('  affected keys: %d (%.2f%%), expected about %.2f%%', $affected, $affected / $keys * 100, 100 / $nodeCount));
        $output->line(sprintf('  fallback matched old second replica: %d of %d', $fallbackMatches, $affected));
        $output->line(sprintf('  unaffected keys kept their primary: %d of %d', $unaffectedMatches, $unaffected));
        $output->line('The replica list is a fallback order, not a replication protocol; actual data movement is outside this model.');
        $output->line(sprintf('Nodes: %d. Keys: %s. Virtual nodes: %d.', $nodeCount, number_format($keys), $virtualNodes));

        $output->measure('affected_keys', $affected);
        $output->measure('affected_fraction', $affected / $keys);
        $output->measure('fallback_matches', $fallbackMatches);
        $output->measure('unaffected_keys', $unaffected);
        $output->measure('unaffected_matches', $unaffectedMatches);
        $output->measure('nodes', $nodeCount);
        $output->measure('keys', $keys);
        $output->measure('virtual_nodes', $virtualNodes);
    },
);
