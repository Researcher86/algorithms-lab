<?php

declare(strict_types=1);

namespace AlgorithmsLab\Foundation;

use OutOfRangeException;

/**
 * Disjoint sets with path compression and union by rank.
 *
 * This is the reference implementation the graph chapter benchmarks against
 * its own variants. Elements are integer ids; the structure stores only the
 * arrays a textbook implementation needs.
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
        }

        while ($this->parent[$element] !== $root) {
            $next = $this->parent[$element];
            $this->parent[$element] = $root;
            $element = $next;
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

        if ($this->rank[$leftRoot] < $this->rank[$rightRoot]) {
            [$leftRoot, $rightRoot] = [$rightRoot, $leftRoot];
        }

        $this->parent[$rightRoot] = $leftRoot;
        $this->size[$leftRoot] += $this->size[$rightRoot];
        --$this->sets;

        if ($this->rank[$leftRoot] === $this->rank[$rightRoot]) {
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
}
