<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Integration;

use AlgorithmsLab\Integration\LogPipeline;
use PHPUnit\Framework\TestCase;

final class LogPipelineTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/algorithms-lab-log-pipeline-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        @rmdir($this->directory);
    }

    public function testBuildsBloomCandidatesTopKAndSortedReport(): void
    {
        $report = $this->directory . '/report.txt';
        $pipeline = new LogPipeline(2, 3, 0.01, $this->directory, 32, 2);

        $result = $pipeline->run(['/b', '/a', '/b', '/c', '/a', '/a'], $report);

        self::assertSame(6, $result->requests);
        self::assertSame(3, $result->distinctUrls);
        self::assertSame($result->bloomNewCandidates, $result->bloomInserted);
        self::assertGreaterThan(0, $result->bloomBytes);
        self::assertSame($result->distinctUrls, $result->bloomNewCandidates + $result->bloomFalsePositives);
        self::assertSame(2, $result->topK);
        self::assertSame([
            ['key' => '/a', 'count' => 3],
            ['key' => '/b', 'count' => 2],
        ], $result->top);
        self::assertSame(3, $result->reportRecords);
        self::assertSame(1, $result->reportRuns);
        self::assertSame(1, $result->reportPasses);
        self::assertFileExists($report);
        self::assertSame(["3\t/a", "2\t/b", "1\t/c"], file($report, FILE_IGNORE_NEW_LINES));
    }

    public function testReportBreaksCountTiesByUrl(): void
    {
        $report = $this->directory . '/ties.txt';
        $pipeline = new LogPipeline(2, 2, 0.01, $this->directory, 32, 2);

        $result = $pipeline->run(['/b', '/a'], $report);

        self::assertSame([
            ['key' => '/a', 'count' => 1],
            ['key' => '/b', 'count' => 1],
        ], $result->top);
        self::assertSame(["1\t/a", "1\t/b"], file($report, FILE_IGNORE_NEW_LINES));
    }

    public function testEmptyStreamStillWritesAnEmptyReport(): void
    {
        $report = $this->directory . '/empty.txt';
        $pipeline = new LogPipeline(3, 1, 0.01, $this->directory, 32, 2);

        $result = $pipeline->run([], $report);

        self::assertSame(0, $result->requests);
        self::assertSame(0, $result->distinctUrls);
        self::assertSame([], $result->top);
        self::assertSame(0, $result->reportRecords);
        self::assertSame(0, $result->reportRuns);
        self::assertSame(0, $result->reportPasses);
        self::assertSame('', file_get_contents($report));
    }
}
