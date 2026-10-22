<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Instrumentation;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CountersTest extends TestCase
{
    public function testStartsAtZeroAndResets(): void
    {
        $counters = new Counters();
        $counters->comparisons = 3;
        $counters->probes = 2;

        self::assertSame(3, $counters->toArray()['comparisons']);
        self::assertSame(2, $counters->toArray()['probes']);

        $counters->reset();

        foreach ($counters->toArray() as $value) {
            self::assertSame(0, $value);
        }
    }

    public function testAddIncrementsANamedCounter(): void
    {
        $counters = new Counters();
        $counters->add('nodeReads', 4);
        $counters->add('nodeReads');
        $counters->add('relinks', 3);

        self::assertSame(5, $counters->nodeReads);
        self::assertSame(3, $counters->relinks);
    }

    public function testUnknownCounterIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Counters()->add('unknown');
    }
}
