<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tree;

/**
 * One node of a B+ tree.
 *
 * Leaf nodes use `keys` as data and `next` as the linked-list pointer.
 * Internal nodes use `keys` as separator keys and `children` as the fanout.
 * A single class keeps the implementation small; `leaf` is the discriminator.
 */
final class BPlusNode
{
    /** @var list<int> */
    public array $keys = [];

    /** @var list<BPlusNode> */
    public array $children = [];

    /** Leaf-only link to the next leaf in key order. */
    public ?BPlusNode $next = null;

    public function __construct(
        public bool $leaf = true,
    ) {
    }
}
