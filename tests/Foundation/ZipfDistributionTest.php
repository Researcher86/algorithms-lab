<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Foundation;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Foundation\ZipfDistribution;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ZipfDistributionTest extends TestCase
{
    public function testSamplesStayInsideTheRankRange(): void
    {
        $distribution = new ZipfDistribution(100, 1.0, new DeterministicRandom('zipf-range'));

        for ($i = 0; $i < 1_000; ++$i) {
            $rank = $distribution->next();

            self::assertGreaterThanOrEqual(1, $rank);
            self::assertLessThanOrEqual(100, $rank);
        }
    }

    public function testTheFirstRankIsMoreFrequentThanTheSecond(): void
    {
        $distribution = new ZipfDistribution(100, 1.0, new DeterministicRandom('zipf-skew'));
        $counts = [0, 0];

        for ($i = 0; $i < 10_000; ++$i) {
            $rank = $distribution->next();

            if ($rank <= 2) {
                ++$counts[$rank - 1];
            }
        }

        self::assertGreaterThan($counts[1], $counts[0]);
    }

    public function testAlphaZeroIsUniform(): void
    {
        $distribution = new ZipfDistribution(10, 0.0, new DeterministicRandom('zipf-uniform'));
        $counts = array_fill(0, 11, 0);

        for ($i = 0; $i < 10_000; ++$i) {
            ++$counts[$distribution->next()];
        }

        for ($rank = 1; $rank <= 10; ++$rank) {
            self::assertGreaterThan(700, $counts[$rank]);
            self::assertLessThan(1_300, $counts[$rank]);
        }
    }

    public function testExposesItsParameters(): void
    {
        $distribution = new ZipfDistribution(50, 0.8, new DeterministicRandom('zipf-parameters'));

        self::assertSame(50, $distribution->items());
        self::assertSame(0.8, $distribution->alpha());
    }

    public function testRejectsAnEmptyDistribution(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ZipfDistribution(0, 1.0, new DeterministicRandom('zipf-empty'));
    }

    public function testRejectsNegativeAlpha(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ZipfDistribution(10, -0.1, new DeterministicRandom('zipf-alpha'));
    }
}
