<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tree;

use AlgorithmsLab\Instrumentation\Counters;

/**
 * The PHP array baseline: integer keys in a hash table.
 *
 * It is not an ordered index. It is here to show how much of the B-tree's
 * memory and search cost buys ordering, range scans, and page locality.
 */
final class PhpArrayIndex
{
    /** @var array<int, true> */
    private array $keys = [];

    public function __construct(
        private readonly ?Counters $counters = null,
    ) {
    }

    public function count(): int
    {
        return count($this->keys);
    }

    public function search(int $key): bool
    {
        $this->counters?->add('comparisons');

        return isset($this->keys[$key]);
    }

    public function insert(int $key): bool
    {
        if (isset($this->keys[$key])) {
            return false;
        }

        $this->keys[$key] = true;

        return true;
    }

    public function delete(int $key): bool
    {
        if (!isset($this->keys[$key])) {
            return false;
        }

        unset($this->keys[$key]);

        return true;
    }

    /**
     * @return list<int>
     */
    public function toSortedArray(): array
    {
        $keys = array_keys($this->keys);
        sort($keys);

        return $keys;
    }
}
