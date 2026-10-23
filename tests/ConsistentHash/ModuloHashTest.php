<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\ConsistentHash;

use AlgorithmsLab\ConsistentHash\ModuloHash;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ModuloHashTest extends TestCase
{
    public function testMapsKeysToConfiguredNodes(): void
    {
        $hash = new ModuloHash();

        self::assertNull($hash->getNode('key'));
        self::assertTrue($hash->addNode('node-a'));
        self::assertTrue($hash->addNode('node-b'));
        self::assertFalse($hash->addNode('node-a'));

        $node = $hash->getNode('key');
        self::assertContains($node, ['node-a', 'node-b']);
        self::assertSame($node, $hash->getNode('key'));
        self::assertSame(2, $hash->nodeCount());
    }

    public function testRemoveNodeChangesTheDivisor(): void
    {
        $hash = new ModuloHash();
        $hash->addNode('node-a');
        $hash->addNode('node-b');
        $hash->addNode('node-c');

        self::assertTrue($hash->removeNode('node-b'));
        self::assertFalse($hash->removeNode('node-b'));

        foreach (['a', 'b', 'c', 'd'] as $key) {
            self::assertContains($hash->getNode($key), ['node-a', 'node-c']);
        }
    }

    public function testRejectsEmptyNodeId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModuloHash()->addNode('');
    }
}
