<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tree;

use InvalidArgumentException;

/**
 * A deliberately small page model for the tree experiments.
 *
 * It does not emulate an operating-system page cache. It estimates how many
 * pages a node occupies from the number of keys and child pointers, and counts
 * one page access per node visited. That is enough to compare a B-tree search
 * with binary search on a flat array: the B-tree pays one page per level, while
 * binary search pays one cache-hostile probe per comparison in the model.
 */
final class PageSimulator
{
    private int $pages = 0;

    public function __construct(
        private readonly int $pageSize = 4096,
        private readonly int $keyBytes = 8,
        private readonly int $pointerBytes = 8,
        private readonly int $headerBytes = 24,
    ) {
        if ($pageSize <= $headerBytes) {
            throw new InvalidArgumentException('Page size must exceed the header');
        }

        if ($keyBytes <= 0 || $pointerBytes <= 0) {
            throw new InvalidArgumentException('Key and pointer sizes must be positive');
        }
    }

    public function reset(): void
    {
        $this->pages = 0;
    }

    public function pagesRead(): int
    {
        return $this->pages;
    }

    public function readNode(int $keys, int $children): void
    {
        $this->readBytes($this->nodeBytes($keys, $children));
    }

    public function readBytes(int $bytes): void
    {
        if ($bytes <= 0) {
            $bytes = 1;
        }

        $this->pages += intdiv($bytes + $this->pageSize - 1, $this->pageSize);
    }

    public function nodeBytes(int $keys, int $children): int
    {
        return $this->headerBytes + $keys * $this->keyBytes + $children * $this->pointerBytes;
    }

    public function maxKeysPerPage(): int
    {
        $payload = $this->pageSize - $this->headerBytes;

        return max(2, intdiv($payload, $this->keyBytes + $this->pointerBytes));
    }

    public function bytesPerKey(): float
    {
        return ($this->pageSize - $this->headerBytes) / $this->maxKeysPerPage();
    }

    public function pageSize(): int
    {
        return $this->pageSize;
    }
}
