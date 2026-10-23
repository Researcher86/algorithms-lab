<?php

declare(strict_types=1);

namespace AlgorithmsLab\Stream;

use AlgorithmsLab\Instrumentation\Counters;
use InvalidArgumentException;

/**
 * Exact top-K by selection from a complete frequency map.
 *
 * Quickselect has average O(n) time and worst-case O(n²); this implementation
 * uses a median-of-three pivot and a deterministic tie-break on the key. It
 * still needs the full map, so it is a batch algorithm, not a bounded-memory
 * stream algorithm.
 */
final class QuickSelect
{
    /**
     * @param array<string, int> $counts
     * @return list<array{key: string, count: int}>
     */
    public static function top(array $counts, int $k, ?Counters $counters = null): array
    {
        if ($k < 1) {
            throw new InvalidArgumentException('K must be at least 1');
        }

        $entries = [];

        foreach ($counts as $key => $count) {
            $entries[] = ['key' => $key, 'count' => $count];
        }

        $total = count($entries);

        if ($total === 0) {
            return [];
        }

        $wanted = min($k, $total);

        if ($wanted < $total) {
            self::select($entries, 0, $total - 1, $wanted, $counters);
        }

        $selected = array_slice($entries, 0, $wanted);
        usort($selected, static function (array $left, array $right) use ($counters): int {
            $counters?->add('comparisons');

            return ($right['count'] <=> $left['count']) ?: strcmp($left['key'], $right['key']);
        });

        return $selected;
    }

    /**
     * @param list<array{key: string, count: int}> $entries
     */
    private static function select(array &$entries, int $left, int $right, int $wanted, ?Counters $counters): void
    {
        if ($left >= $right) {
            return;
        }

        $pivot = self::partition($entries, $left, $right, $counters);
        $leftSize = $pivot - $left;

        if ($wanted < $leftSize) {
            self::select($entries, $left, $pivot - 1, $wanted, $counters);
        } elseif ($wanted > $leftSize + 1) {
            self::select($entries, $pivot + 1, $right, $wanted - $leftSize - 1, $counters);
        }
    }

    /**
     * @param list<array{key: string, count: int}> $entries
     */
    private static function partition(array &$entries, int $left, int $right, ?Counters $counters): int
    {
        $middle = intdiv($left + $right, 2);

        if (self::isBetter($entries[$middle], $entries[$left], $counters)) {
            self::swap($entries, $middle, $left);
        }

        if (self::isBetter($entries[$right], $entries[$left], $counters)) {
            self::swap($entries, $right, $left);
        }

        if (self::isBetter($entries[$right], $entries[$middle], $counters)) {
            self::swap($entries, $right, $middle);
        }

        self::swap($entries, $middle, $right);
        $pivot = $entries[$right];
        $store = $left;

        for ($index = $left; $index < $right; ++$index) {
            if (self::isBetter($entries[$index], $pivot, $counters)) {
                self::swap($entries, $store, $index);
                ++$store;
            }
        }

        self::swap($entries, $store, $right);

        return $store;
    }

    /**
     * @param array{key: string, count: int} $left
     * @param array{key: string, count: int} $right
     */
    private static function isBetter(array $left, array $right, ?Counters $counters): bool
    {
        $counters?->add('comparisons');

        if ($left['count'] !== $right['count']) {
            return $left['count'] > $right['count'];
        }

        return strcmp($left['key'], $right['key']) < 0;
    }

    /**
     * @param list<array{key: string, count: int}> $entries
     */
    private static function swap(array &$entries, int $left, int $right): void
    {
        if ($left === $right) {
            return;
        }

        [$entries[$left], $entries[$right]] = [$entries[$right], $entries[$left]];
    }
}
