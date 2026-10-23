<?php

declare(strict_types=1);

namespace AlgorithmsLab\Integration;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Foundation\ZipfDistribution;
use Generator;
use InvalidArgumentException;

/**
 * A reproducible access-log URL stream.
 *
 * The stream yields URLs rather than materializing a request list: the log
 * pipeline is supposed to consume a stream, and replaying it is the caller's
 * responsibility when a second pass is needed. Popularity is Zipf-distributed
 * so the top-K report has a realistic head.
 */
final class LogStream
{
    /**
     * @return Generator<int, string>
     */
    public static function zipfUrls(
        int $requests,
        int $urls,
        float $alpha,
        DeterministicRandom $random,
    ): Generator {
        if ($requests < 0) {
            throw new InvalidArgumentException('Request count cannot be negative');
        }

        if ($urls < 1) {
            throw new InvalidArgumentException('URL alphabet must be at least 1');
        }

        if ($alpha < 0.0) {
            throw new InvalidArgumentException('Zipf alpha cannot be negative');
        }

        $distribution = new ZipfDistribution($urls, $alpha, $random);

        for ($index = 0; $index < $requests; ++$index) {
            yield 'https://example.test/p/' . $distribution->next();
        }
    }
}
