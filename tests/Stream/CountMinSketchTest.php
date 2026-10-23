<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Stream;

use AlgorithmsLab\Instrumentation\Counters;
use AlgorithmsLab\Stream\CountMinSketch;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CountMinSketchTest extends TestCase
{
    public function testEstimatesAFrequency(): void
    {
        $sketch = new CountMinSketch(64, 4);
        $sketch->add('a', 3);
        $sketch->add('b');
        $sketch->add('b');

        self::assertSame(3, $sketch->estimate('a'));
        self::assertSame(2, $sketch->estimate('b'));
        self::assertSame(0, $sketch->estimate('missing'));
        self::assertSame(256, $sketch->cells());
        self::assertSame(64, $sketch->width());
        self::assertSame(4, $sketch->depth());
    }

    public function testCollisionsOverestimateButNeverUnderestimate(): void
    {
        $sketch = new CountMinSketch(1, 1);
        $sketch->add('a', 5);
        $sketch->add('b', 7);

        self::assertSame(12, $sketch->estimate('a'));
        self::assertSame(12, $sketch->estimate('b'));
        self::assertGreaterThanOrEqual(5, $sketch->estimate('a'));
    }

    public function testCountersRecordHashesAndProbes(): void
    {
        $counters = new Counters();
        $sketch = new CountMinSketch(32, 3, $counters);
        $sketch->add('a', 2);
        $sketch->estimate('a');

        self::assertSame(6, $counters->hashCalls);
        self::assertSame(6, $counters->probes);
    }

    public function testRejectsInvalidDimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CountMinSketch(0);
    }

    public function testRejectsInvalidDepth(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CountMinSketch(4, 0);
    }

    public function testRejectsNonPositiveIncrement(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CountMinSketch(4)->add('a', 0);
    }
}
