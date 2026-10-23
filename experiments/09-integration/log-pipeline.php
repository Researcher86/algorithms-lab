<?php

declare(strict_types=1);

use AlgorithmsLab\Experiment\Experiment;
use AlgorithmsLab\Experiment\Options;
use AlgorithmsLab\Experiment\Output;
use AlgorithmsLab\ExternalSort\LineReader;
use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Integration\LogPipeline;
use AlgorithmsLab\Integration\LogStream;

return new Experiment(
    name: 'scenario:log-pipeline',
    description: 'Access-log stream -> Bloom first-seen detector -> exact top-K -> externally sorted daily report.',
    supports: ['requests', 'urls', 'top', 'seed'],
    run: static function (Options $options, Output $output): void {
        $root = dirname(__DIR__, 2);
        $requests = max(1, $options->requests(100_000));
        $urls = max(1, $options->urls(20_000));
        $top = max(1, $options->top(10));
        $seed = $options->seed('book');
        $reportDirectory = $root . '/var/results';

        if (!is_dir($reportDirectory) && !mkdir($reportDirectory, 0o777, true) && !is_dir($reportDirectory)) {
            throw new RuntimeException(sprintf('Cannot create report directory: %s', $reportDirectory));
        }

        $reportPath = $reportDirectory . '/log-pipeline-report.txt';
        $pipeline = new LogPipeline($top, $urls, 0.01, $root . '/var/tmp');
        $result = $pipeline->run(
            LogStream::zipfUrls($requests, $urls, 1.1, new DeterministicRandom($seed)),
            $reportPath,
        );

        $output->heading(
            'Access-log pipeline',
            'A stream of requests passes Bloom for first-seen URLs, exact top-K counting and an external report sort.',
        );
        $output->line(sprintf(
            'Input: %s requests over %s URL ranks (Zipf alpha 1.1), seed %s.',
            number_format($result->requests),
            number_format($urls),
            $seed,
        ));
        $output->line();
        $output->line(sprintf('%-18s %s', 'Bloom bits', number_format($result->bloomBits)));
        $output->line(sprintf('%-18s %.1f KiB', 'Bloom bytes', $result->bloomBytes / 1024));
        $output->line(sprintf('%-18s %d', 'Bloom hashes', $result->bloomHashes));
        $output->line(sprintf('%-18s %.4f', 'Bit density', $result->bloomBitDensity));
        $output->line(sprintf('%-18s %d', 'URLs inserted', $result->bloomInserted));
        $output->line(sprintf('%-18s %d', 'New candidates', $result->bloomNewCandidates));
        $output->line(sprintf('%-18s %d', 'Exact distinct', $result->distinctUrls));
        $output->line(sprintf('%-18s %d', 'False positives', $result->bloomFalsePositives));
        $output->line(sprintf('%-18s %.4f', 'Observed FPR', $result->bloomObservedFalsePositiveRate));
        $output->line(sprintf('%-18s %.4f', 'Estimated FPR', $result->bloomEstimatedFalsePositiveRate));
        $output->line(sprintf('%-18s %.3f s', 'Ingest time', $result->ingestSeconds));
        $output->line(sprintf('%-18s %.1f MiB', 'Ingest peak', $result->ingestPeakBytes / 1024 / 1024));
        $output->line();
        $output->line(sprintf('Top %d URLs (exact heap, exact count map):', $result->topK));

        foreach ($result->top as $index => $entry) {
            $output->line(sprintf('%2d. %-42s %s', $index + 1, $entry['key'], number_format($entry['count'])));
        }

        $output->line();
        $output->line(sprintf('%-18s %d', 'Report records', $result->reportRecords));
        $output->line(sprintf('%-18s %d', 'Sorted runs', $result->reportRuns));
        $output->line(sprintf('%-18s %d', 'Merge passes', $result->reportPasses));
        $output->line(sprintf('%-18s %.1f MiB', 'Bytes read', $result->reportBytesRead / 1024 / 1024));
        $output->line(sprintf('%-18s %.1f MiB', 'Bytes written', $result->reportBytesWritten / 1024 / 1024));
        $output->line(sprintf('%-18s %d', 'Comparisons', $result->reportComparisons));
        $output->line(sprintf('%-18s %.3f s', 'Sort time', $result->sortSeconds));
        $output->line(sprintf('%-18s %.1f MiB', 'Sort peak', $result->sortPeakBytes / 1024 / 1024));
        $output->line(sprintf('%-18s %s', 'Report', $result->reportPath));
        $output->line();
        $output->line('Report head (count, URL):');

        $shown = 0;

        foreach (LineReader::lines($result->reportPath) as $line) {
            $output->line('  ' . $line);
            ++$shown;

            if ($shown === 5) {
                break;
            }
        }

        $output->line();
        $output->line('Bloom is a first-seen detector: the exact distinct count comes from TopKHeap, so false positives are measured, not assumed.');
        $output->line('TopKHeap keeps an exact count for every distinct URL; the heap is bounded by K, the count map is not.');
        $output->line('The report is sorted by count descending and URL ascending with a 32 KiB chunk and fan-in 8; it stays on disk under var/results.');
        $output->measure('requests', $result->requests);
        $output->measure('urls_alphabet', $urls);
        $output->measure('top_k', $result->topK);
        $output->measure('distinct_urls', $result->distinctUrls);
        $output->measure('bloom_bits', $result->bloomBits);
        $output->measure('bloom_bytes', $result->bloomBytes);
        $output->measure('bloom_hashes', $result->bloomHashes);
        $output->measure('bloom_bit_density', $result->bloomBitDensity);
        $output->measure('bloom_target_fpr', $result->bloomTargetFalsePositiveRate);
        $output->measure('bloom_estimated_fpr', $result->bloomEstimatedFalsePositiveRate);
        $output->measure('bloom_inserted', $result->bloomInserted);
        $output->measure('bloom_new_candidates', $result->bloomNewCandidates);
        $output->measure('bloom_false_positives', $result->bloomFalsePositives);
        $output->measure('bloom_observed_fpr', $result->bloomObservedFalsePositiveRate);
        $output->measure('ingest_seconds', $result->ingestSeconds);
        $output->measure('ingest_delta_bytes', $result->ingestDeltaBytes);
        $output->measure('ingest_peak_bytes', $result->ingestPeakBytes);
        $output->measure('report_records', $result->reportRecords);
        $output->measure('report_runs', $result->reportRuns);
        $output->measure('report_passes', $result->reportPasses);
        $output->measure('report_bytes_read', $result->reportBytesRead);
        $output->measure('report_bytes_written', $result->reportBytesWritten);
        $output->measure('report_io_bytes', $result->reportBytesRead + $result->reportBytesWritten);
        $output->measure('report_comparisons', $result->reportComparisons);
        $output->measure('sort_seconds', $result->sortSeconds);
        $output->measure('sort_peak_bytes', $result->sortPeakBytes);
        $output->measure('report_path', $result->reportPath);
        $output->measure('seed', $seed);
    },
);
