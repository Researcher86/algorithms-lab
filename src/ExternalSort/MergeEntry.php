<?php

declare(strict_types=1);

namespace AlgorithmsLab\ExternalSort;

/**
 * One head record in the k-way merge, tagged with its run number.
 *
 * The run number is the stable-order tie-break: when the user comparator says
 * two records are equal, the record from the earlier run is emitted first.
 */
final readonly class MergeEntry
{
    public function __construct(
        public string $value,
        public int $run,
    ) {
    }
}
