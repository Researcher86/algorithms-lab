<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Tree;

use AlgorithmsLab\Tree\PageSimulator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PageSimulatorTest extends TestCase
{
    public function testCountsPagesForNodeSizes(): void
    {
        $pages = new PageSimulator(4_096);

        $pages->readNode(2, 3);
        self::assertSame(1, $pages->pagesRead());

        $pages->readBytes(4_097);
        self::assertSame(3, $pages->pagesRead());

        $pages->reset();
        self::assertSame(0, $pages->pagesRead());
    }

    public function testDerivesFanoutAndBytesPerKey(): void
    {
        $pages = new PageSimulator(4_096, 8, 8, 24);

        self::assertSame(254, $pages->maxKeysPerPage());
        self::assertEqualsWithDelta(16.031, $pages->bytesPerKey(), 0.001);
        self::assertSame(4_096, $pages->pageSize());
        self::assertSame(24 + 8 * 10 + 8 * 11, $pages->nodeBytes(10, 11));
    }

    public function testRejectsAnImpossiblePageSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PageSimulator(16, 8, 8, 24);
    }
}
