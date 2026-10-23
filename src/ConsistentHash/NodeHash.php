<?php

declare(strict_types=1);

namespace AlgorithmsLab\ConsistentHash;

/**
 * The minimum contract shared by modulo hashing, a consistent-hash ring and
 * rendezvous hashing. The experiments can then change the algorithm without
 * changing the mapping loop.
 */
interface NodeHash
{
    public function addNode(string $node): bool;

    public function removeNode(string $node): bool;

    public function getNode(string $key): ?string;

    public function nodeCount(): int;
}
