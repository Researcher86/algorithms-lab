<?php

declare(strict_types=1);

namespace AlgorithmsLab\Instrumentation;

use InvalidArgumentException;

/**
 * The operation counts a chapter reports beside its timings.
 *
 * Time says how fast this machine ran. These counters say what the algorithm
 * actually did, which is what a complexity claim is about. They are public
 * integers so a hot loop can increment them without a method call; reset()
 * delimits one measured operation.
 */
final class Counters
{
    public int $comparisons = 0;
    public int $probes = 0;
    public int $hashCalls = 0;
    public int $collisions = 0;
    public int $nodeReads = 0;
    public int $swaps = 0;
    public int $relinks = 0;
    public int $rehashes = 0;

    public function reset(): void
    {
        $this->comparisons = 0;
        $this->probes = 0;
        $this->hashCalls = 0;
        $this->collisions = 0;
        $this->nodeReads = 0;
        $this->swaps = 0;
        $this->relinks = 0;
        $this->rehashes = 0;
    }

    public function add(string $name, int $amount = 1): void
    {
        match ($name) {
            'comparisons' => $this->comparisons += $amount,
            'probes' => $this->probes += $amount,
            'hashCalls' => $this->hashCalls += $amount,
            'collisions' => $this->collisions += $amount,
            'nodeReads' => $this->nodeReads += $amount,
            'swaps' => $this->swaps += $amount,
            'relinks' => $this->relinks += $amount,
            'rehashes' => $this->rehashes += $amount,
            default => throw new InvalidArgumentException(sprintf('Unknown counter: %s', $name)),
        };
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'comparisons' => $this->comparisons,
            'probes' => $this->probes,
            'hashCalls' => $this->hashCalls,
            'collisions' => $this->collisions,
            'nodeReads' => $this->nodeReads,
            'swaps' => $this->swaps,
            'relinks' => $this->relinks,
            'rehashes' => $this->rehashes,
        ];
    }
}
