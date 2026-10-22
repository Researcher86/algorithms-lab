<?php

declare(strict_types=1);

namespace AlgorithmsLab\Foundation;

use InvalidArgumentException;

/**
 * A deterministic Zipf sampler over ranks 1..n.
 *
 * The CDF is built once with weights 1 / rank^alpha and sampled by binary
 * search. This is the standard way to ask a cache experiment "what happens when
 * a few keys are much more popular than the rest?" without hand-writing a
 * request list that accidentally encodes the answer.
 *
 * The sampler returns ranks, not cache keys; the experiment maps them.
 */
final class ZipfDistribution
{
    /** @var list<float> */
    private array $cumulative;

    private readonly float $total;

    public function __construct(
        private readonly int $items,
        private readonly float $alpha,
        private readonly DeterministicRandom $random,
    ) {
        if ($items < 1) {
            throw new InvalidArgumentException('Zipf distribution needs at least one item');
        }

        if ($alpha < 0.0) {
            throw new InvalidArgumentException('Zipf alpha cannot be negative');
        }

        $this->cumulative = [];
        $total = 0.0;

        for ($rank = 1; $rank <= $items; ++$rank) {
            $total += 1.0 / ($rank ** $alpha);
            $this->cumulative[] = $total;
        }

        $this->total = $total;
    }

    /**
     * @return int A rank in [1, items].
     */
    public function next(): int
    {
        $target = $this->random->nextFloat() * $this->total;
        $low = 0;
        $high = count($this->cumulative) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);

            if ($this->cumulative[$middle] < $target) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low + 1;
    }

    public function items(): int
    {
        return $this->items;
    }

    public function alpha(): float
    {
        return $this->alpha;
    }
}
