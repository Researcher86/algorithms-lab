<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tree;

/**
 * One node of the textbook B-tree.
 *
 * Internal nodes have one more child than keys; leaf nodes have no children.
 * The arrays are deliberately plain: the chapter measures the cost of the
 * algorithm, not a node abstraction layer.
 */
final class BTreeNode
{
    /** @var array<int, int> */
    public array $keys = [];

    /** @var array<int, BTreeNode> */
    public array $children = [];

    public function __construct(
        public bool $leaf = true,
    ) {
    }
}
