<?php

declare(strict_types=1);

namespace AlgorithmsLab\ExternalSort;

/**
 * What one external sort actually did.
 *
 * Byte counts include the input, every spilled run and the output. They are
 * logical record bytes, not filesystem blocks. Comparisons are the number of
 * calls through the sorter's comparator, including chunk sorts and merges.
 */
final readonly class SortStats
{
    public function __construct(
        public int $records,
        public int $runs,
        public int $passes,
        public int $bytesRead,
        public int $bytesWritten,
        public int $comparisons,
        public int $chunkBytes,
        public int $fanIn,
    ) {
    }

    public function totalBytes(): int
    {
        return $this->bytesRead + $this->bytesWritten;
    }
}
