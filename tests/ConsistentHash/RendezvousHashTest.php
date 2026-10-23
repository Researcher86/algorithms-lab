<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\ConsistentHash;

use AlgorithmsLab\ConsistentHash\RendezvousHash;
use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RendezvousHashTest extends TestCase
{
    public function testHighestScoreWins(): void
    {
        $hash = new RendezvousHash();
        $hash->addNode('node-a');
        $hash->addNode('node-b');
        $hash->addNode('node-c');

        $node = $hash->getNode('customer-1');

        self::assertNotNull($node);
        self::assertContains($node, ['node-a', 'node-b', 'node-c']);
        self::assertSame($node, $hash->getNode('customer-1'));
        self::assertSame(3, $hash->nodeCount());
    }

    public function testRemoveNodeOnlyAffectsItsKeys(): void
    {
        $hash = new RendezvousHash();
        $hash->addNode('node-a');
        $hash->addNode('node-b');
        $hash->addNode('node-c');

        $mapping = [];

        for ($index = 0; $index < 100; ++$index) {
            $mapping['key-' . $index] = $hash->getNode('key-' . $index);
        }

        self::assertTrue($hash->removeNode('node-b'));
        self::assertFalse($hash->removeNode('node-b'));

        $changed = 0;

        foreach ($mapping as $key => $oldNode) {
            $newNode = $hash->getNode($key);

            if ($oldNode !== $newNode) {
                ++$changed;
            }

            self::assertNotSame('node-b', $newNode);
        }

        self::assertGreaterThan(0, $changed);
    }

    public function testCountersRecordCandidateHashes(): void
    {
        $counters = new Counters();
        $hash = new RendezvousHash($counters);
        $hash->addNode('node-a');
        $hash->addNode('node-b');
        $counters->reset();

        $hash->getNode('key');

        self::assertSame(2, $counters->hashCalls);
        self::assertSame(2, $counters->comparisons);
    }

    public function testRejectsEmptyNodeId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RendezvousHash()->addNode('');
    }
}
