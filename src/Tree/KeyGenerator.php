<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tree;

use AlgorithmsLab\Foundation\DeterministicRandom;

/**
 * Reproducible integer key sets for tree experiments.
 *
 * The values are dense (0 through n - 1) so binary search and the tree see the
 * same key space. Shuffling is Fisher-Yates with the lab's deterministic
 * generator; the result is a list, never a hash-order accident.
 */
final class KeyGenerator
{
    /**
     * @return list<int>
     */
    public static function shuffled(int $count, string $seed): array
    {
        $keys = self::ascending($count);
        $random = new DeterministicRandom($seed);

        for ($index = $count - 1; $index > 0; --$index) {
            $other = $random->intBetween(0, $index);
            [$keys[$index], $keys[$other]] = [$keys[$other], $keys[$index]];
        }

        return array_values($keys);
    }

    /**
     * @return list<int>
     */
    public static function ascending(int $count): array
    {
        if ($count === 0) {
            return [];
        }

        return range(0, $count - 1);
    }

    /**
     * @return list<int>
     */
    public static function descending(int $count): array
    {
        return array_reverse(self::ascending($count));
    }
}
