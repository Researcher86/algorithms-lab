<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tree;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * A sorted PHP list with binary search.
 *
 * This is the flat-array baseline: search is O(log n), but insertion and
 * deletion move O(n) elements. It is intentionally simple; the B-tree is
 * compared against it rather than against another tree.
 */
final class SortedArrayIndex
{
    /** @var list<int> */
    private array $keys = [];

    public function __construct(
        private readonly ?Counters $counters = null,
    ) {
    }

    /**
     * @param list<int> $keys
     */
    public static function fromSortedArray(array $keys, ?Counters $counters = null): self
    {
        $previous = null;

        foreach ($keys as $key) {
            if ($previous !== null && $key <= $previous) {
                throw new InvalidArgumentException('Sorted-array keys must be sorted and unique');
            }

            $previous = $key;
        }

        $index = new self($counters);

        foreach ($keys as $key) {
            $index->keys[] = $key;
        }

        return $index;
    }

    public function count(): int
    {
        return count($this->keys);
    }

    public function search(int $key): bool
    {
        $index = $this->lowerBound($key);

        return $index < count($this->keys) && $this->keys[$index] === $key;
    }

    public function insert(int $key): bool
    {
        $index = $this->lowerBound($key);

        if ($index < count($this->keys) && $this->keys[$index] === $key) {
            return false;
        }

        array_splice($this->keys, $index, 0, [$key]);

        return true;
    }

    public function delete(int $key): bool
    {
        $index = $this->lowerBound($key);

        if ($index >= count($this->keys) || $this->keys[$index] !== $key) {
            return false;
        }

        array_splice($this->keys, $index, 1);

        return true;
    }

    /**
     * @return list<int>
     */
    public function toSortedArray(): array
    {
        return $this->keys;
    }

    private function lowerBound(int $key): int
    {
        $low = 0;
        $high = count($this->keys);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $this->counters?->add('comparisons');

            if ($this->keys[$middle] < $key) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }
}
