<?php

declare(strict_types=1);

namespace AlgorithmsLab\Foundation;

use AlgorithmsLab\Instrumentation\Counters;
use OutOfRangeException;

/**
 * Disjoint sets with optional path compression and union by rank.
 *
 * This is the reference implementation the graph chapter benchmarks against
 * its own variants. Elements are integer ids; the structure stores only the
 * arrays a textbook implementation needs. The flags exist so the chapter can
 * isolate what each optimization buys without maintaining three copies of the
 * same code.
 */
final class UnionFind
{
    /** @var array<int, int> */
    private array $parent = [];

    /** @var array<int, int> */
    private array $rank = [];

    /** @var array<int, int> */
    private array $size = [];

    private int $sets = 0;

    public function __construct(
        private readonly bool $pathCompression = true,
        private readonly bool $unionByRank = true,
        private readonly ?Counters $counters = null,
    ) {
    }

    public function makeSet(int $element): void
    {
        if (isset($this->parent[$element])) {
            return;
        }

        $this->parent[$element] = $element;
        $this->rank[$element] = 0;
        $this->size[$element] = 1;
        ++$this->sets;
    }

    public function find(int $element): int
    {
        $this->assertKnown($element);
        $root = $element;

        while ($this->parent[$root] !== $root) {
            $root = $this->parent[$root];
            $this->readNode();
        }

        if (!$this->pathCompression) {
            return $root;
        }

        while ($this->parent[$element] !== $root) {
            $next = $this->parent[$element];
            $this->parent[$element] = $root;
            $element = $next;
            $this->readNode();
        }

        return $root;
    }

    /**
     * @return bool True when the sets were different and were merged.
     */
    public function union(int $left, int $right): bool
    {
        $leftRoot = $this->find($left);
        $rightRoot = $this->find($right);

        if ($leftRoot === $rightRoot) {
            return false;
        }

        if ($this->unionByRank) {
            if ($this->rank[$leftRoot] < $this->rank[$rightRoot]) {
                [$leftRoot, $rightRoot] = [$rightRoot, $leftRoot];
            }
        }

        $this->parent[$rightRoot] = $leftRoot;
        $this->size[$leftRoot] += $this->size[$rightRoot];
        --$this->sets;

        if ($this->unionByRank && $this->rank[$leftRoot] === $this->rank[$rightRoot]) {
            ++$this->rank[$leftRoot];
        }

        return true;
    }

    public function connected(int $left, int $right): bool
    {
        return $this->find($left) === $this->find($right);
    }

    public function componentSize(int $element): int
    {
        return $this->size[$this->find($element)];
    }

    public function setCount(): int
    {
        return $this->sets;
    }

    private function assertKnown(int $element): void
    {
        if (!isset($this->parent[$element])) {
            throw new OutOfRangeException(sprintf('Unknown union-find element: %d', $element));
        }
    }

    private function readNode(): void
    {
        if ($this->counters !== null) {
            ++$this->counters->nodeReads;
        }
    }
}
