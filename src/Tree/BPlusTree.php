<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tree;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;
use LogicException;

/**
 * A B+ tree with linked leaves and a sorted bulk-load path.
 *
 * Internal separators are the first key of the child to their right, so a
 * lookup takes the right child on equality. Leaves hold every key exactly once
 * and are linked in ascending order; range scans follow that chain instead of
 * returning to the root. `minimumDegree` has the same meaning as in BTree:
 * at most 2t - 1 keys per node, at least t - 1 except at the root.
 */
final class BPlusTree
{
    private BPlusNode $root;

    private int $size = 0;

    private readonly int $maximumKeys;

    private readonly int $maximumChildren;

    public function __construct(
        private readonly int $minimumDegree = 3,
        private readonly ?Counters $counters = null,
    ) {
        if ($minimumDegree < 2) {
            throw new InvalidArgumentException('Minimum degree must be at least 2');
        }

        $this->maximumKeys = 2 * $minimumDegree - 1;
        $this->maximumChildren = 2 * $minimumDegree;
        $this->root = new BPlusNode();
    }

    /**
     * Build a B+ tree in one pass from sorted, unique keys.
     *
     * The bulk loader packs leaves to capacity and builds the internal levels
     * bottom-up. The final node at a level may be underfull; this is the usual
     * bulk-load trade-off and is why `validate()` does not impose the minimum
     * occupancy on a bulk-loaded tree.
     *
     * @param list<int> $keys
     */
    public static function bulkLoad(int $minimumDegree, array $keys, ?Counters $counters = null): self
    {
        $tree = new self($minimumDegree, $counters);
        $previous = null;

        foreach ($keys as $key) {
            if ($previous !== null && $key <= $previous) {
                throw new InvalidArgumentException('Bulk-load keys must be sorted and unique');
            }

            $previous = $key;
        }

        if ($keys === []) {
            return $tree;
        }

        /** @var list<BPlusNode> $level */
        $level = [];
        $previousLeaf = null;

        foreach (array_chunk($keys, max(1, $tree->maximumKeys)) as $chunk) {
            $leaf = new BPlusNode();
            $leaf->keys = $chunk;

            if ($previousLeaf !== null) {
                $previousLeaf->next = $leaf;
            }

            $level[] = $leaf;
            $previousLeaf = $leaf;
        }

        while (count($level) > 1) {
            $parents = [];
            $groups = $tree->groupChildren($level);

            foreach ($groups as $group) {
                $parent = new BPlusNode(false);

                foreach ($group as $offset => $child) {
                    if ($offset > 0) {
                        $parent->keys[] = self::firstKey($child);
                    }

                    $parent->children[] = $child;
                }

                $parents[] = $parent;
            }

            $level = $parents;
        }

        $tree->root = $level[0];
        $tree->size = count($keys);

        return $tree;
    }

    public function minimumDegree(): int
    {
        return $this->minimumDegree;
    }

    public function count(): int
    {
        return $this->size;
    }

    public function search(int $key, ?PageSimulator $pages = null): bool
    {
        $node = $this->root;

        while (true) {
            $this->counters?->add('nodeReads');
            $pages?->readNode(count($node->keys), count($node->children));

            if ($node->leaf) {
                $index = $this->lowerBound($node->keys, $key);

                return $index < count($node->keys) && $node->keys[$index] === $key;
            }

            $node = $node->children[$this->upperBound($node->keys, $key)];
        }
    }

    public function insert(int $key): bool
    {
        if ($this->search($key)) {
            return false;
        }

        $split = $this->insertInto($this->root, $key);

        if ($split !== null) {
            $newRoot = new BPlusNode(false);
            $newRoot->keys = [$split['key']];
            $newRoot->children = [$this->root, $split['node']];
            $this->root = $newRoot;
        }

        ++$this->size;

        return true;
    }

    /**
     * @return list<int>
     */
    public function range(int $from, int $to, ?PageSimulator $pages = null): array
    {
        if ($from > $to) {
            return [];
        }

        $node = $this->root;

        while (!$node->leaf) {
            $this->counters?->add('nodeReads');
            $pages?->readNode(count($node->keys), count($node->children));
            $node = $node->children[$this->upperBound($node->keys, $from)];
        }

        $result = [];
        $first = true;

        while ($node !== null) {
            $this->counters?->add('nodeReads');
            $pages?->readNode(count($node->keys), 0);
            $start = $first ? $this->lowerBound($node->keys, $from) : 0;
            $first = false;

            for ($index = $start; $index < count($node->keys); ++$index) {
                $key = $node->keys[$index];

                if ($key > $to) {
                    return $result;
                }

                $result[] = $key;
            }

            $node = $node->next;
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    public function toSortedArray(): array
    {
        $node = $this->root;

        while (!$node->leaf) {
            $node = $node->children[0];
        }

        $keys = [];

        while ($node !== null) {
            foreach ($node->keys as $key) {
                $keys[] = $key;
            }

            $node = $node->next;
        }

        return $keys;
    }

    public function height(): int
    {
        $height = 1;
        $node = $this->root;

        while (!$node->leaf) {
            ++$height;
            $node = $node->children[0];
        }

        return $height;
    }

    public function validate(bool $strictOccupancy = true): void
    {
        $this->validateNode($this->root, true, $strictOccupancy);
    }

    /**
     * @return array{key:int,node:BPlusNode}|null
     */
    private function insertInto(BPlusNode $node, int $key): ?array
    {
        $this->counters?->add('nodeReads');

        if ($node->leaf) {
            $index = $this->lowerBound($node->keys, $key);
            array_splice($node->keys, $index, 0, [$key]);

            if (count($node->keys) <= $this->maximumKeys) {
                return null;
            }

            return $this->splitLeaf($node);
        }

        $index = $this->upperBound($node->keys, $key);
        $split = $this->insertInto($node->children[$index], $key);

        if ($split === null) {
            return null;
        }

        array_splice($node->keys, $index, 0, [$split['key']]);
        array_splice($node->children, $index + 1, 0, [$split['node']]);

        if (count($node->keys) <= $this->maximumKeys) {
            return null;
        }

        return $this->splitInternal($node);
    }

    /**
     * @return array{key:int,node:BPlusNode}
     */
    private function splitLeaf(BPlusNode $node): array
    {
        $right = new BPlusNode();
        $middle = $this->minimumDegree;
        $right->keys = array_slice($node->keys, $middle);
        $node->keys = array_slice($node->keys, 0, $middle);

        $right->next = $node->next;
        $node->next = $right;

        return ['key' => $right->keys[0], 'node' => $right];
    }

    /**
     * @return array{key:int,node:BPlusNode}
     */
    private function splitInternal(BPlusNode $node): array
    {
        $right = new BPlusNode(false);
        $middle = $this->minimumDegree;
        $promoted = $node->keys[$middle];

        $right->keys = array_slice($node->keys, $middle + 1);
        $node->keys = array_slice($node->keys, 0, $middle);
        $right->children = array_slice($node->children, $middle + 1);
        $node->children = array_slice($node->children, 0, $middle + 1);

        return ['key' => $promoted, 'node' => $right];
    }

    private static function firstKey(BPlusNode $node): int
    {
        while (!$node->leaf) {
            $node = $node->children[0];
        }

        return $node->keys[0];
    }

    /**
     * @param list<BPlusNode> $nodes
     * @return list<list<BPlusNode>>
     */
    private function groupChildren(array $nodes): array
    {
        $groups = [];
        $count = count($nodes);
        $offset = 0;

        while ($offset < $count) {
            $remaining = $count - $offset;
            $size = min($this->maximumChildren, $remaining);

            // Avoid a final unary internal node. Redistributing one child
            // keeps every non-root internal node usefully populated.
            if ($remaining > $this->maximumChildren && $remaining - $this->maximumChildren === 1) {
                $size = $this->maximumChildren - 1;
            }

            $groups[] = array_slice($nodes, $offset, $size);
            $offset += $size;
        }

        return $groups;
    }

    private function validateNode(BPlusNode $node, bool $root, bool $strictOccupancy): int
    {
        $keyCount = count($node->keys);

        if ($keyCount > $this->maximumKeys) {
            throw new LogicException('Node contains too many keys');
        }

        for ($index = 1; $index < $keyCount; ++$index) {
            if ($node->keys[$index - 1] >= $node->keys[$index]) {
                throw new LogicException('Node keys are not strictly increasing');
            }
        }

        if ($node->leaf) {
            if ($node->children !== []) {
                throw new LogicException('Leaf node has children');
            }

            if ($node->next !== null && ($node->keys === [] || $node->next->keys === [] || $node->keys[count($node->keys) - 1] >= $node->next->keys[0])) {
                throw new LogicException('Leaf links are not in key order');
            }

            return 1;
        }

        if ($keyCount === 0 || count($node->children) !== $keyCount + 1) {
            throw new LogicException('Internal node has the wrong number of children');
        }

        $depth = null;

        foreach ($node->children as $child) {
            $childDepth = $this->validateNode($child, false, $strictOccupancy);

            if ($depth === null) {
                $depth = $childDepth;
            } elseif ($depth !== $childDepth) {
                throw new LogicException('Leaves are at different depths');
            }
        }

        if ($strictOccupancy && !$root && $keyCount < $this->minimumDegree - 1) {
            throw new LogicException('Non-root node contains too few keys');
        }

        return $depth + 1;
    }

    /**
     * @param list<int> $keys
     */
    private function lowerBound(array $keys, int $key): int
    {
        $low = 0;
        $high = count($keys);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $this->counters?->add('comparisons');

            if ($keys[$middle] < $key) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    /**
     * @param list<int> $keys
     */
    private function upperBound(array $keys, int $key): int
    {
        $low = 0;
        $high = count($keys);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $this->counters?->add('comparisons');

            if ($keys[$middle] <= $key) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }
}
