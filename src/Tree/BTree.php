<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tree;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;
use LogicException;

/**
 * A B-tree with CLRS minimum-degree semantics.
 *
 * `minimumDegree` is the textbook t: a node holds at most 2t - 1 keys and,
 * except for the root, at least t - 1. The implementation uses the
 * top-down split on insertion and the pre-emptive borrow/merge on deletion.
 * Keys are unique integers; inserting an existing key is a no-op.
 */
final class BTree
{
    private BTreeNode $root;

    private int $size = 0;

    private readonly int $maximumKeys;

    public function __construct(
        private readonly int $minimumDegree = 3,
        private readonly ?Counters $counters = null,
    ) {
        if ($minimumDegree < 2) {
            throw new InvalidArgumentException('Minimum degree must be at least 2');
        }

        $this->maximumKeys = 2 * $minimumDegree - 1;
        $this->root = new BTreeNode();
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

            $index = $this->lowerBound($node, $key);

            if ($index < count($node->keys) && $node->keys[$index] === $key) {
                return true;
            }

            if ($node->leaf) {
                return false;
            }

            $node = $node->children[$index];
        }
    }

    public function insert(int $key): bool
    {
        if (count($this->root->keys) === $this->maximumKeys) {
            $newRoot = new BTreeNode(false);
            $newRoot->children = [$this->root];
            $this->root = $newRoot;
            $this->splitChild($newRoot, 0);
        }

        $inserted = $this->insertNonFull($this->root, $key);

        if ($inserted) {
            ++$this->size;
        }

        return $inserted;
    }

    public function delete(int $key): bool
    {
        $deleted = $this->deleteFrom($this->root, $key);

        if (!$deleted) {
            return false;
        }

        --$this->size;

        if (!$this->root->leaf && count($this->root->keys) === 0) {
            $this->root = $this->root->children[0];
        }

        return true;
    }

    /**
     * @return list<int>
     */
    public function toSortedArray(): array
    {
        $keys = [];
        $this->collect($this->root, $keys);

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

    public function validate(): void
    {
        $this->validateNode($this->root, true);
    }

    private function insertNonFull(BTreeNode $node, int $key): bool
    {
        $this->counters?->add('nodeReads');

        $index = $this->lowerBound($node, $key);

        if ($index < count($node->keys) && $node->keys[$index] === $key) {
            return false;
        }

        if ($node->leaf) {
            array_splice($node->keys, $index, 0, [$key]);

            return true;
        }

        $child = $node->children[$index];

        if (count($child->keys) === $this->maximumKeys) {
            $this->splitChild($node, $index);
            $median = $node->keys[$index];

            if ($median === $key) {
                return false;
            }

            if ($key > $median) {
                ++$index;
            }

            $child = $node->children[$index];
        }

        return $this->insertNonFull($child, $key);
    }

    private function splitChild(BTreeNode $parent, int $index): void
    {
        $child = $parent->children[$index];
        $right = new BTreeNode($child->leaf);
        $middle = $this->minimumDegree - 1;
        $median = $child->keys[$middle];

        $right->keys = array_slice($child->keys, $middle + 1);
        $child->keys = array_slice($child->keys, 0, $middle);

        if (!$child->leaf) {
            $right->children = array_slice($child->children, $middle + 1);
            $child->children = array_slice($child->children, 0, $middle + 1);
        }

        array_splice($parent->keys, $index, 0, [$median]);
        array_splice($parent->children, $index + 1, 0, [$right]);
    }

    private function deleteFrom(BTreeNode $node, int $key): bool
    {
        $this->counters?->add('nodeReads');

        $index = $this->lowerBound($node, $key);

        if ($index < count($node->keys) && $node->keys[$index] === $key) {
            if ($node->leaf) {
                array_splice($node->keys, $index, 1);

                return true;
            }

            $this->deleteInternal($node, $index);

            return true;
        }

        if ($node->leaf) {
            return false;
        }

        $child = $node->children[$index];

        if (count($child->keys) < $this->minimumDegree) {
            if ($index > 0 && count($node->children[$index - 1]->keys) >= $this->minimumDegree) {
                $this->borrowFromLeft($node, $index);
            } elseif ($index < count($node->keys) && count($node->children[$index + 1]->keys) >= $this->minimumDegree) {
                $this->borrowFromRight($node, $index);
            } elseif ($index < count($node->keys)) {
                $this->mergeChildren($node, $index);
            } else {
                $this->mergeChildren($node, $index - 1);
                --$index;
            }
        }

        return $this->deleteFrom($node->children[$index], $key);
    }

    private function deleteInternal(BTreeNode $node, int $index): void
    {
        $key = $node->keys[$index];
        $left = $node->children[$index];
        $right = $node->children[$index + 1];

        if (count($left->keys) >= $this->minimumDegree) {
            $predecessor = $this->maximumKey($left);
            $node->keys[$index] = $predecessor;
            $this->deleteFrom($left, $predecessor);

            return;
        }

        if (count($right->keys) >= $this->minimumDegree) {
            $successor = $this->minimumKey($right);
            $node->keys[$index] = $successor;
            $this->deleteFrom($right, $successor);

            return;
        }

        $this->mergeChildren($node, $index);
        $this->deleteFrom($left, $key);
    }

    private function borrowFromLeft(BTreeNode $parent, int $index): void
    {
        $child = $parent->children[$index];
        $left = $parent->children[$index - 1];

        array_unshift($child->keys, $parent->keys[$index - 1]);
        $borrowedKey = array_pop($left->keys);

        if ($borrowedKey === null) {
            throw new LogicException('Cannot borrow from an empty left sibling');
        }

        $parent->keys[$index - 1] = $borrowedKey;

        if (!$child->leaf) {
            $borrowedChild = array_pop($left->children);

            if ($borrowedChild === null) {
                throw new LogicException('Cannot borrow a child from an empty left sibling');
            }

            array_unshift($child->children, $borrowedChild);
        }
    }

    private function borrowFromRight(BTreeNode $parent, int $index): void
    {
        $child = $parent->children[$index];
        $right = $parent->children[$index + 1];

        $child->keys[] = $parent->keys[$index];
        $borrowedKey = array_shift($right->keys);

        if ($borrowedKey === null) {
            throw new LogicException('Cannot borrow from an empty right sibling');
        }

        $parent->keys[$index] = $borrowedKey;

        if (!$child->leaf) {
            $borrowedChild = array_shift($right->children);

            if ($borrowedChild === null) {
                throw new LogicException('Cannot borrow a child from an empty right sibling');
            }

            $child->children[] = $borrowedChild;
        }
    }

    private function mergeChildren(BTreeNode $parent, int $index): void
    {
        $left = $parent->children[$index];
        $right = $parent->children[$index + 1];

        $left->keys[] = $parent->keys[$index];
        foreach ($right->keys as $key) {
            $left->keys[] = $key;
        }

        if (!$left->leaf) {
            foreach ($right->children as $child) {
                $left->children[] = $child;
            }
        }

        array_splice($parent->keys, $index, 1);
        array_splice($parent->children, $index + 1, 1);
    }

    private function minimumKey(BTreeNode $node): int
    {
        $this->counters?->add('nodeReads');

        while (!$node->leaf) {
            $node = $node->children[0];
            $this->counters?->add('nodeReads');
        }

        return $node->keys[0];
    }

    private function maximumKey(BTreeNode $node): int
    {
        $this->counters?->add('nodeReads');

        while (!$node->leaf) {
            $node = $node->children[count($node->children) - 1];
            $this->counters?->add('nodeReads');
        }

        return $node->keys[count($node->keys) - 1];
    }

    /**
     * @param list<int> $keys
     */
    private function collect(BTreeNode $node, array &$keys): void
    {
        if ($node->leaf) {
            foreach ($node->keys as $key) {
                $keys[] = $key;
            }

            return;
        }

        foreach ($node->keys as $index => $key) {
            $this->collect($node->children[$index], $keys);
            $keys[] = $key;
        }

        $this->collect($node->children[count($node->children) - 1], $keys);
    }

    private function lowerBound(BTreeNode $node, int $key): int
    {
        $low = 0;
        $high = count($node->keys);

        while ($low < $high) {
            $middle = intdiv($low + $high, 2);
            $this->counters?->add('comparisons');

            if ($node->keys[$middle] < $key) {
                $low = $middle + 1;
            } else {
                $high = $middle;
            }
        }

        return $low;
    }

    private function validateNode(BTreeNode $node, bool $root): int
    {
        $keyCount = count($node->keys);

        if ($keyCount > $this->maximumKeys) {
            throw new LogicException('Node contains too many keys');
        }

        if (!$root && $keyCount < $this->minimumDegree - 1) {
            throw new LogicException('Non-root node contains too few keys');
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

            return 1;
        }

        if ($keyCount === 0) {
            throw new LogicException('Internal node has no keys');
        }

        if (count($node->children) !== $keyCount + 1) {
            throw new LogicException('Internal node has the wrong number of children');
        }

        $depth = null;

        foreach ($node->children as $child) {
            $childDepth = $this->validateNode($child, false);

            if ($depth === null) {
                $depth = $childDepth;
            } elseif ($depth !== $childDepth) {
                throw new LogicException('Leaves are at different depths');
            }
        }

        return $depth + 1;
    }
}
