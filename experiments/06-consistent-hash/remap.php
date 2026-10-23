<?php

declare(strict_types=1);

use AlgorithmsLab\ConsistentHash\HashRing;
use AlgorithmsLab\ConsistentHash\ModuloHash;
use AlgorithmsLab\ConsistentHash\NodeHash;
use AlgorithmsLab\ConsistentHash\RendezvousHash;
use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;

return new Experiment(
    name: 'ring:remap',
    description: 'How many keys move when one node is added or removed.',
    supports: ['elements', 'size', 'capacity', 'seed'],
    run: static function (Options $options, Output $output): void {
        $keys = $options->elements(50_000);
        $nodeCount = max(2, $options->size(10));
        $virtualNodes = max(1, $options->capacity(100));
        $seed = $options->seed('ring-remap');
        $keyList = [];

        for ($index = 0; $index < $keys; ++$index) {
            $keyList[] = $seed . '-key-' . $index;
        }

        $map = static function (NodeHash $hash, array $keyList): array {
            $mapping = [];

            foreach ($keyList as $key) {
                $mapping[$key] = $hash->getNode($key);
            }

            return $mapping;
        };
        $changedFraction = static function (array $before, array $after): float {
            $changed = 0;

            foreach ($before as $key => $node) {
                if (($after[$key] ?? null) !== $node) {
                    ++$changed;
                }
            }

            return $changed / count($before);
        };

        $output->heading(
            'Ring — remapping after a node change',
            'A good consistent hash moves about 1/(n + 1) of keys when one node joins or leaves.',
        );
        $output->line(sprintf('%-14s %-8s %12s %12s', 'algorithm', 'change', 'fraction', 'ideal'));
        $output->line(str_repeat('-', 50));

        foreach (['modulo', 'ring', 'rendezvous'] as $algorithm) {
            $hash = match ($algorithm) {
                'modulo' => new ModuloHash(),
                'ring' => new HashRing($virtualNodes),
                'rendezvous' => new RendezvousHash(),
            };

            for ($index = 0; $index < $nodeCount; ++$index) {
                $hash->addNode('node-' . $index);
            }

            $before = $map($hash, $keyList);
            $hash->addNode('node-' . $nodeCount);
            $afterAdd = $map($hash, $keyList);
            $added = $changedFraction($before, $afterAdd);
            $ideal = 1 / ($nodeCount + 1);

            $output->line(sprintf(
                '%-14s %-8s %11.1f%% %11.1f%%',
                $algorithm,
                'add',
                $added * 100,
                $ideal * 100,
            ));
            $output->measure($algorithm . '_add_fraction', $added);

            $beforeRemove = $afterAdd;
            $hash->removeNode('node-0');
            $afterRemove = $map($hash, $keyList);
            $removed = $changedFraction($beforeRemove, $afterRemove);

            $output->line(sprintf(
                '%-14s %-8s %11.1f%% %11.1f%%',
                $algorithm,
                'remove',
                $removed * 100,
                $ideal * 100,
            ));
            $output->measure($algorithm . '_remove_fraction', $removed);
        }

        $output->line();
        $output->line('Modulo moves nearly every key because the divisor changes; the ring and rendezvous move only the affected share.');
        $output->line('The ideal is the fraction of keys that were owned by the changed node, not a promise of perfect balance.');
        $output->line(sprintf(
            'Keys: %s. Nodes: %d. Virtual nodes per node: %d. Seed: %s.',
            number_format($keys),
            $nodeCount,
            $virtualNodes,
            $seed,
        ));

        $output->measure('keys', $keys);
        $output->measure('nodes', $nodeCount);
        $output->measure('virtual_nodes', $virtualNodes);
        $output->measure('seed', $seed);
    },
);
