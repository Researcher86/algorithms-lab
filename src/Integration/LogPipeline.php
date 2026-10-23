<?php

declare(strict_types=1);

namespace AlgorithmsLab\Integration;

use AlgorithmsLab\BloomFilter\BloomFilter;
use AlgorithmsLab\ExternalSort\ExternalSorter;
use AlgorithmsLab\Instrumentation\Measure;
use AlgorithmsLab\Stream\TopKHeap;
use Closure;
use Generator;
use InvalidArgumentException;
use LogicException;

/**
 * One access-log pass through Bloom uniqueness, exact top-K and an external
 * report sort.
 *
 * The Bloom filter is a first-seen detector, not a source of truth. A URL is
 * inserted only when the filter says it has not been seen, and the exact
 * distinct count comes from TopKHeap's count map; that lets the report compare
 * Bloom's "new" candidates with the exact first occurrences without maintaining
 * a separate validation set. TopKHeap is exact and keeps a count for every
 * distinct URL even though its heap is bounded.
 *
 * The report is written as `count<TAB>url` records and sorted externally by
 * count descending, then URL ascending. This is intentionally a separate stage:
 * the pipeline already has the counts, but the daily report must be produced in
 * a bounded-memory sorted form.
 */
final class LogPipeline
{
    public function __construct(
        private readonly int $topK,
        private readonly int $expectedDistinctUrls,
        private readonly float $bloomFalsePositiveRate,
        private readonly string $tempDirectory,
        private readonly int $reportChunkBytes = 32 * 1024,
        private readonly int $reportFanIn = 8,
    ) {
        if ($this->topK < 1) {
            throw new InvalidArgumentException('Top-K must be at least 1');
        }

        if ($this->expectedDistinctUrls < 1) {
            throw new InvalidArgumentException('Expected distinct URL count must be at least 1');
        }

        if ($this->bloomFalsePositiveRate <= 0.0 || $this->bloomFalsePositiveRate >= 1.0) {
            throw new InvalidArgumentException('Bloom false-positive rate must be in (0, 1)');
        }

        if ($this->reportChunkBytes < 1) {
            throw new InvalidArgumentException('Report chunk size must be at least 1 byte');
        }

        if ($this->reportFanIn < 2) {
            throw new InvalidArgumentException('Report fan-in must be at least 2');
        }
    }

    /**
     * @param iterable<string> $urls
     */
    public function run(iterable $urls, string $reportPath): LogPipelineResult
    {
        $ingest = new Measure();
        $ingest->start();
        $bloom = new BloomFilter($this->expectedDistinctUrls, $this->bloomFalsePositiveRate);
        $topK = new TopKHeap($this->topK);
        $requests = 0;
        $newCandidates = 0;

        foreach ($urls as $url) {
            if (!$bloom->mightContain($url)) {
                ++$newCandidates;
                $bloom->add($url);
            }

            $topK->observe($url);
            ++$requests;
        }

        $ingest->stop();
        $counts = $topK->counts();
        $distinctUrls = count($counts);
        $falsePositives = max(0, $distinctUrls - $newCandidates);

        $sort = new Measure();
        $sort->start();
        $sorter = new ExternalSorter(
            $this->tempDirectory,
            $this->reportChunkBytes,
            $this->reportFanIn,
            self::reportComparator(),
        );
        $stats = $sorter->sort(self::reportRecords($counts), $reportPath);
        $sort->stop();

        return new LogPipelineResult(
            requests: $requests,
            distinctUrls: $distinctUrls,
            bloomBits: $bloom->bitCount(),
            bloomBytes: intdiv($bloom->bitCount() + 7, 8),
            bloomHashes: $bloom->hashCount(),
            bloomBitDensity: $bloom->bitDensity(),
            bloomTargetFalsePositiveRate: $bloom->targetFalsePositiveRate(),
            bloomEstimatedFalsePositiveRate: $bloom->estimatedFalsePositiveRate(),
            bloomInserted: $bloom->inserted(),
            bloomNewCandidates: $newCandidates,
            bloomFalsePositives: $falsePositives,
            bloomObservedFalsePositiveRate: $distinctUrls === 0
                ? 0.0
                : $falsePositives / $distinctUrls,
            ingestSeconds: $ingest->elapsedSeconds(),
            ingestDeltaBytes: $ingest->deltaBytes(),
            ingestPeakBytes: max(0, $ingest->memory->peak() - $ingest->memory->baseline()),
            topK: $topK->k(),
            top: $topK->top(),
            reportRecords: $stats->records,
            reportRuns: $stats->runs,
            reportPasses: $stats->passes,
            reportBytesRead: $stats->bytesRead,
            reportBytesWritten: $stats->bytesWritten,
            reportComparisons: $stats->comparisons,
            sortSeconds: $sort->elapsedSeconds(),
            sortPeakBytes: max(0, $sort->memory->peak() - $sort->memory->baseline()),
            reportPath: $reportPath,
        );
    }

    /**
     * @param array<string, int> $counts
     *
     * @return Generator<int, string>
     */
    private static function reportRecords(array $counts): Generator
    {
        foreach ($counts as $url => $count) {
            yield $count . "\t" . $url;
        }
    }

    /**
     * @return Closure(string, string): int
     */
    private static function reportComparator(): Closure
    {
        return static function (string $left, string $right): int {
            [$leftCount, $leftUrl] = self::splitReportRecord($left);
            [$rightCount, $rightUrl] = self::splitReportRecord($right);
            $byCount = $rightCount <=> $leftCount;

            return $byCount !== 0 ? $byCount : strcmp($leftUrl, $rightUrl);
        };
    }

    /**
     * @return array{0: int, 1: string}
     */
    private static function splitReportRecord(string $record): array
    {
        $separator = strpos($record, "\t");

        if ($separator === false) {
            throw new LogicException('Report records must be "count<TAB>url"');
        }

        return [(int) substr($record, 0, $separator), substr($record, $separator + 1)];
    }
}
