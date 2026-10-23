<?php

declare(strict_types=1);

namespace AlgorithmsLab\Integration;

/**
 * The observable outcome of one access-log pipeline run.
 *
 * The fields separate the three structures deliberately: Bloom counters and
 * memory, the exact top-K answer, and the external sorter's logical I/O. A
 * caller can report them without reaching back into the pipeline's internals.
 */
final readonly class LogPipelineResult
{
    /**
     * @param list<array{key: string, count: int}> $top
     */
    public function __construct(
        public int $requests,
        public int $distinctUrls,
        public int $bloomBits,
        public int $bloomBytes,
        public int $bloomHashes,
        public float $bloomBitDensity,
        public float $bloomTargetFalsePositiveRate,
        public float $bloomEstimatedFalsePositiveRate,
        public int $bloomInserted,
        public int $bloomNewCandidates,
        public int $bloomFalsePositives,
        public float $bloomObservedFalsePositiveRate,
        public float $ingestSeconds,
        public int $ingestDeltaBytes,
        public int $ingestPeakBytes,
        public int $topK,
        public array $top,
        public int $reportRecords,
        public int $reportRuns,
        public int $reportPasses,
        public int $reportBytesRead,
        public int $reportBytesWritten,
        public int $reportComparisons,
        public float $sortSeconds,
        public int $sortPeakBytes,
        public string $reportPath,
    ) {
    }
}
