<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Integration;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Integration\LogStream;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LogStreamTest extends TestCase
{
    public function testProducesDeterministicUrlsWithinTheAlphabet(): void
    {
        $first = iterator_to_array(LogStream::zipfUrls(100, 10, 1.1, new DeterministicRandom('stream-test')), false);
        $second = iterator_to_array(LogStream::zipfUrls(100, 10, 1.1, new DeterministicRandom('stream-test')), false);

        self::assertCount(100, $first);
        self::assertSame($first, $second);

        foreach ($first as $url) {
            self::assertMatchesRegularExpression('/^https:\/\/example\.test\/p\/\d+$/', $url);
            $rank = (int) substr($url, strrpos($url, '/') + 1);
            self::assertGreaterThanOrEqual(1, $rank);
            self::assertLessThanOrEqual(10, $rank);
        }
    }

    public function testRejectsNegativeRequestCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        iterator_to_array(LogStream::zipfUrls(-1, 10, 1.1, new DeterministicRandom('test')));
    }

    public function testRejectsEmptyUrlAlphabet(): void
    {
        $this->expectException(InvalidArgumentException::class);

        iterator_to_array(LogStream::zipfUrls(1, 0, 1.1, new DeterministicRandom('test')));
    }
}
