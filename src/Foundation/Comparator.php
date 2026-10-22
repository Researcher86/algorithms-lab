<?php

declare(strict_types=1);

namespace AlgorithmsLab\Foundation;

use Closure;

/**
 * A comparator that counts how many times it was asked to compare.
 *
 * Algorithms in this lab are judged by what they do, not only by how long the
 * machine took. Wrapping the comparison makes that count automatic and keeps
 * the counters out of the data structures themselves.
 */
final class Comparator
{
    private int $comparisons = 0;

    /**
     * @param Closure(mixed, mixed): int $comparator
     */
    private function __construct(
        private readonly Closure $comparator,
    ) {
    }

    /**
     * @param Closure(mixed, mixed): int $comparator
     */
    public static function of(Closure $comparator): self
    {
        return new self($comparator);
    }

    public static function integers(): self
    {
        return new self(static fn (mixed $left, mixed $right): int => (int) $left <=> (int) $right);
    }

    public function compare(mixed $left, mixed $right): int
    {
        ++$this->comparisons;

        return ($this->comparator)($left, $right);
    }

    public function comparisons(): int
    {
        return $this->comparisons;
    }

    public function reset(): void
    {
        $this->comparisons = 0;
    }
}
