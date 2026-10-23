<?php

declare(strict_types=1);

namespace AlgorithmsLab\ConsistentHash;

use AlgorithmsLab\Foundation\Hashers;
use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * Rendezvous (highest-random-weight) hashing.
 *
 * Each node and key produce one score. The highest score wins. A node change
 * only affects keys whose winner was that node, so the remapping probability
 * is about 1/(n + 1), the same ideal as a well-balanced ring, without a ring
 * or virtual nodes. The cost is O(n) hashes per lookup.
 */
final class RendezvousHash implements NodeHash
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
        $winner = null;
        $bestScore = -1;

        foreach ($this->nodes as $node) {
            $this->counters?->add('hashCalls');
            $this->counters?->add('comparisons');
            $score = Hashers::xxh128($key . ':' . $node);

            if ($score > $bestScore || ($score === $bestScore && ($winner === null || $node < $winner))) {
                $winner = $node;
                $bestScore = $score;
            }
        }

        return $winner;
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
