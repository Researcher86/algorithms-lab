<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\ConsistentHash;

use AlgorithmsLab\ConsistentHash\HashRing;
use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HashRingTest extends TestCase
{
    public function testEmptyRing(): void
    {
        $ring = new HashRing(4);

        self::assertNull($ring->getNode('key'));
        self::assertSame([], $ring->getNodes('key', 2));
        self::assertSame(0, $ring->nodeCount());
        self::assertSame(0, $ring->virtualNodeCount());
    }

    public function testVirtualNodesAndStableLookup(): void
    {
        $ring = new HashRing(5);

        self::assertTrue($ring->addNode('node-a'));
        self::assertTrue($ring->addNode('node-b'));
        self::assertTrue($ring->addNode('node-c'));
        self::assertFalse($ring->addNode('node-b'));
        self::assertSame(3, $ring->nodeCount());
        self::assertSame(15, $ring->virtualNodeCount());

        $first = $ring->getNode('customer-42');
        self::assertNotNull($first);
        self::assertSame($first, $ring->getNode('customer-42'));
        self::assertContains($first, ['node-a', 'node-b', 'node-c']);
    }

    public function testGetNodesReturnsDistinctOwnersInOrder(): void
    {
        $ring = new HashRing(10);
        $ring->addNode('node-a');
        $ring->addNode('node-b');
        $ring->addNode('node-c');

        $nodes = $ring->getNodes('key-123', 2);

        self::assertCount(2, $nodes);
        self::assertCount(2, array_unique($nodes));
        self::assertSame($ring->getNode('key-123'), $nodes[0]);
        self::assertCount(3, $ring->getNodes('key-123', 10));
    }

    public function testRemoveNodeRemovesItsVirtualNodes(): void
    {
        $ring = new HashRing(4);
        $ring->addNode('node-a');
        $ring->addNode('node-b');
        $ring->addNode('node-c');

        self::assertTrue($ring->removeNode('node-b'));
        self::assertFalse($ring->removeNode('node-b'));
        self::assertSame(2, $ring->nodeCount());
        self::assertSame(8, $ring->virtualNodeCount());

        foreach (['one', 'two', 'three', 'four'] as $key) {
            self::assertNotSame('node-b', $ring->getNode($key));
        }
    }

    public function testRemovingPrimaryMovesKeyToItsFallback(): void
    {
        $ring = new HashRing(20);
        $ring->addNode('node-a');
        $ring->addNode('node-b');
        $ring->addNode('node-c');
        $ring->addNode('node-d');

        for ($index = 0; $index < 50; ++$index) {
            $key = 'customer-' . $index;
            $replicas = $ring->getNodes($key, 2);
            $primary = $replicas[0];
            $fallback = $replicas[1];
            $ring->removeNode($primary);

            self::assertSame($fallback, $ring->getNode($key));
            $ring->addNode($primary);
        }
    }

    public function testCountersRecordRingWork(): void
    {
        $counters = new Counters();
        $ring = new HashRing(10, $counters);
        $ring->addNode('node-a');
        $ring->addNode('node-b');
        $counters->reset();

        self::assertNotNull($ring->getNode('key'));
        self::assertGreaterThan(0, $counters->hashCalls);
        self::assertGreaterThan(0, $counters->comparisons);
    }

    public function testRejectsInvalidConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HashRing(0);
    }

    public function testRejectsEmptyNodeId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HashRing()->addNode('');
    }
}
