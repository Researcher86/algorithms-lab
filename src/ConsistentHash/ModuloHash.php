<?php

declare(strict_types=1);

namespace AlgorithmsLab\ConsistentHash;

use AlgorithmsLab\Foundation\Hashers;
use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * The baseline: hash the key and take it modulo the current node count.
 *
 * It is simple and perfectly uniform while the node set is fixed. Adding or
 * removing one node changes the divisor for almost every key, which is the
 * behavior the rest of the chapter is trying to avoid.
 */
final class ModuloHash implements NodeHash
{
    /** @var list<string> */
    private array $nodes = [];

    public function __construct(
        private readonly ?Counters $counters = null,
    ) {
    }

    public function addNode(string $node): bool
    {
        $this->assertNode($node);

        if (in_array($node, $this->nodes, true)) {
            return false;
        }

        $this->nodes[] = $node;

        return true;
    }

    public function removeNode(string $node): bool
    {
        $index = array_search($node, $this->nodes, true);

        if ($index === false) {
            return false;
        }

        array_splice($this->nodes, $index, 1);

        return true;
    }

    public function getNode(string $key): ?string
    {
        $count = count($this->nodes);

        if ($count === 0) {
            return null;
        }

        $this->counters?->add('hashCalls');

        return $this->nodes[Hashers::xxh128($key) % $count];
    }

    public function nodeCount(): int
    {
        return count($this->nodes);
    }

    /**
     * @return list<string>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    private function assertNode(string $node): void
    {
        if ($node === '') {
            throw new InvalidArgumentException('Node id cannot be empty');
        }
    }
}
