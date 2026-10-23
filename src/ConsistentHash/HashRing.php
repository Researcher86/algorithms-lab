<?php

declare(strict_types=1);

namespace AlgorithmsLab\ConsistentHash;

use AlgorithmsLab\Foundation\Hashers;
use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * A consistent-hash ring with virtual nodes and binary-search lookup.
 *
 * Each node contributes `virtualNodes` tokens at hashed positions. A key maps
 * to the first token at or after its own hash, wrapping around. Removing one
 * node therefore moves only the keys that landed on that node's tokens; all
 * other keys keep their previous owner. More virtual nodes smooth the
 * distribution but increase the ring size and the add/remove work.
 */
final class HashRing implements NodeHash
{
    private const SPACE = 1_152_921_504_606_846_976;

    /** @var array<int, string> */
    private array $ring = [];

    /** @var list<int> */
    private array $positions = [];

    /** @var array<string, list<int>> */
    private array $nodePositions = [];

    public function __construct(
        private readonly int $virtualNodes = 100,
        private readonly ?Counters $counters = null,
    ) {
        if ($virtualNodes < 1) {
            throw new InvalidArgumentException('Virtual node count must be at least 1');
        }
    }

    public function addNode(string $node): bool
    {
        if ($node === '') {
            throw new InvalidArgumentException('Node id cannot be empty');
        }

        if (isset($this->nodePositions[$node])) {
            return false;
        }

        $positions = [];

        for ($replica = 0; $replica < $this->virtualNodes; ++$replica) {
            $this->counters?->add('hashCalls');
            $position = Hashers::xxh128($node . '#' . $replica);

            while (isset($this->ring[$position])) {
                $position = ($position + 1) % self::SPACE;
            }

            $this->ring[$position] = $node;
            $positions[] = $position;
        }

        $this->nodePositions[$node] = $positions;
        $this->rebuildPositions();

        return true;
    }

    public function removeNode(string $node): bool
    {
        if (!isset($this->nodePositions[$node])) {
            return false;
        }

        foreach ($this->nodePositions[$node] as $position) {
            unset($this->ring[$position]);
        }

        unset($this->nodePositions[$node]);
        $this->rebuildPositions();

        return true;
    }

    public function getNode(string $key): ?string
    {
        if ($this->positions === []) {
            return null;
        }

        $this->counters?->add('hashCalls');
        $position = $this->firstPositionAtOrAfter(Hashers::xxh128($key));

        return $this->ring[$position];
    }

    /**
     * Return the first distinct owners encountered clockwise from the key.
     *
     * This is the replica-selection operation: the first entry is the primary,
     * the second is the fallback when the primary fails, and so on. Duplicate
     * virtual nodes owned by the same physical node are skipped.
     *
     * @return list<string>
     */
    public function getNodes(string $key, int $count): array
    {
        if ($count < 1 || $this->positions === []) {
            return [];
        }

        $wanted = min($count, count($this->nodePositions));

        if ($wanted === 0) {
            return [];
        }

        $this->counters?->add('hashCalls');
        $index = $this->firstPositionIndexAtOrAfter(Hashers::xxh128($key));
        $total = count($this->positions);
        $seen = [];
        $nodes = [];
        $visited = 0;

        while (count($nodes) < $wanted && $visited < $total) {
            $node = $this->ring[$this->positions[$index]];

            if (!isset($seen[$node])) {
                $seen[$node] = true;
                $nodes[] = $node;
            }

            $index = ($index + 1) % $total;
            ++$visited;
        }

        return $nodes;
    }

    public function nodeCount(): int
    {
        return count($this->nodePositions);
    }

    public function virtualNodeCount(): int
    {
        return count($this->positions);
    }

    /**
     * @return list<string>
     */
    public function nodes(): array
    {
        return array_keys($this->nodePositions);
    }

    private function firstPositionAtOrAfter(int $hash): int
    {
        return $this->positions[$this->firstPositionIndexAtOrAfter($hash)];
    }

    private function firstPositionIndexAtOrAfter(int $hash): int
    {
        $low = 0;
        $high = count($this->positions);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $this->counters?->add('comparisons');

            if ($this->positions[$middle] < $hash) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low === count($this->positions) ? 0 : $low;
    }

    private function rebuildPositions(): void
    {
        $this->positions = array_keys($this->ring);
        sort($this->positions);
    }
}
