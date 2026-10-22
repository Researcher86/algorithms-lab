<?php

declare(strict_types=1);

namespace AlgorithmsLab\Graph;

/**
 * The result of one depth-first traversal from a single source.
 *
 * `order` is preorder; `postorder` is the order in which frames finished.
 * Reverse postorder is a topological order for a DAG.
 */
final readonly class DfsResult
{
    /**
     * @param list<int> $order
     * @param list<int> $postorder
     * @param array<int, int|null> $parents
     */
    public function __construct(
        public array $order,
        public array $postorder,
        public array $parents,
    ) {
    }
}
