<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Cache;

use AlgorithmsLab\Cache\ArrayLRU;
use AlgorithmsLab\Cache\NaiveLRU;
use AlgorithmsLab\Foundation\DeterministicRandom;
use PHPUnit\Framework\TestCase;

final class ArrayAndNaiveLRUTest extends TestCase
{
    public function testArrayLruUsesInsertionOrder(): void
    {
        $cache = new ArrayLRU(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->get('a');
        $cache->put('c', 3);

        self::assertFalse($cache->has('b'));
        self::assertSame(['a', 'c'], $cache->keys());
    }

    public function testNaiveLruUsesListOrder(): void
    {
        $cache = new NaiveLRU(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->get('a');
        $cache->put('c', 3);

        self::assertFalse($cache->has('b'));
        self::assertSame(['a', 'c'], $cache->keys());
    }

    public function testNaiveCountsComparisons(): void
    {
        $cache = new NaiveLRU(4);
        $cache->put('a', 1);
        $cache->get('a');
        $cache->get('missing');

        self::assertGreaterThan(0, $cache->counters()->comparisons);
    }

    public function testArrayAndNaiveMatchTheLruModel(): void
    {
        foreach ([new ArrayLRU(8), new NaiveLRU(8)] as $cache) {
            $random = new DeterministicRandom('array-naive-' . $cache::class);
            $capacity = 8;
            $order = [];
            $values = [];

            for ($step = 0; $step < 500; ++$step) {
                $key = 'key-' . $random->intBetween(0, 20);
                $operation = $random->intBetween(0, 2);

                if ($operation === 0) {
                    $value = $random->intBetween(0, 1_000_000);
                    $cache->put($key, $value);

                    if (array_key_exists($key, $values)) {
                        $position = array_search($key, $order, true);

                        if ($position !== false) {
                            unset($order[$position]);
                            $order = array_values($order);
                        }
                    }

                    $order[] = $key;
                    $values[$key] = $value;

                    if (count($order) > $capacity) {
                        $evicted = array_shift($order);
                        unset($values[$evicted]);
                    }

                    continue;
                }

                if ($operation === 1) {
                    self::assertSame(array_key_exists($key, $values), $cache->remove($key));

                    if (array_key_exists($key, $values)) {
                        $position = array_search($key, $order, true);

                        if ($position !== false) {
                            unset($order[$position]);
                            $order = array_values($order);
                        }

                        unset($values[$key]);
                    }

                    continue;
                }

                if (!array_key_exists($key, $values)) {
                    self::assertNull($cache->get($key));

                    continue;
                }

                $position = array_search($key, $order, true);

                if ($position !== false) {
                    unset($order[$position]);
                    $order = array_values($order);
                }

                $order[] = $key;
                self::assertSame($values[$key], $cache->get($key));
            }

            self::assertSame(count($values), $cache->count());
            self::assertSame($order, $cache->keys());
        }
    }
}
