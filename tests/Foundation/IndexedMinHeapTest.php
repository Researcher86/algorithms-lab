<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Foundation;

use AlgorithmsLab\Foundation\IndexedMinHeap;
use PHPUnit\Framework\TestCase;
use UnderflowException;

final class IndexedMinHeapTest extends TestCase
{
    public function testExtractsInPriorityOrderWithVertexTies(): void
    {
        $heap = new IndexedMinHeap();
        $heap->insertOrDecrease(3, 5.0);
        $heap->insertOrDecrease(1, 2.0);
        $heap->insertOrDecrease(2, 2.0);

        self::assertSame(3, $heap->count());
        self::assertSame(['vertex' => 1, 'priority' => 2.0], $heap->extractMin());
        self::assertSame(['vertex' => 2, 'priority' => 2.0], $heap->extractMin());
        self::assertSame(['vertex' => 3, 'priority' => 5.0], $heap->extractMin());
        self::assertTrue($heap->isEmpty());
    }

    public function testDecreaseKeyMovesAnExistingVertex(): void
    {
        $heap = new IndexedMinHeap();
        $heap->insertOrDecrease(0, 100.0);
        $heap->insertOrDecrease(1, 50.0);
        $heap->insertOrDecrease(0, 10.0);

        self::assertTrue($heap->contains(0));
        self::assertSame(10.0, $heap->priorityOf(0));
        self::assertSame(2, $heap->count());
        self::assertSame(0, $heap->extractMin()['vertex']);
    }

    public function testRaisingAPriorityIsIgnored(): void
    {
        $heap = new IndexedMinHeap();
        $heap->insertOrDecrease(0, 10.0);
        $heap->insertOrDecrease(0, 20.0);

        self::assertSame(10.0, $heap->priorityOf(0));
    }

    public function testUnknownPriorityIsNull(): void
    {
        self::assertNull(new IndexedMinHeap()->priorityOf(9));
    }

    public function testEmptyHeapCannotBeExtracted(): void
    {
        $this->expectException(UnderflowException::class);

        new IndexedMinHeap()->extractMin();
    }
}
