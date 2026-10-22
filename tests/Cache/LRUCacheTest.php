<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Cache;

use AlgorithmsLab\Cache\LRUCache;
use AlgorithmsLab\Foundation\DeterministicRandom;
use PHPUnit\Framework\TestCase;

final class LRUCacheTest extends TestCase
{
    public function testEvictsTheLeastRecentlyUsedEntry(): void
    {
        $cache = new LRUCache(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->get('a');
        $cache->put('c', 3);

        self::assertFalse($cache->has('b'));
        self::assertTrue($cache->has('a'));
        self::assertTrue($cache->has('c'));
        self::assertSame(['c', 'a'], $cache->keys());
    }

    public function testUpdateMovesTheEntryToTheFront(): void
    {
        $cache = new LRUCache(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->put('a', 3);
        $cache->put('c', 4);

        self::assertFalse($cache->has('b'));
        self::assertSame(['c', 'a'], $cache->keys());
    }

    public function testCountersRecordPointerWork(): void
    {
        $cache = new LRUCache(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->get('a');

        self::assertGreaterThan(0, $cache->counters()->relinks);
    }

    public function testMatchesAPlainLruModelUnderRandomOperations(): void
    {
        $random = new DeterministicRandom('lru-differential');
        $capacity = 8;
        $cache = new LRUCache($capacity);
        $order = [];
        $values = [];

        for ($step = 0; $step < 1_000; ++$step) {
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
        self::assertSame(array_reverse($order), $cache->keys());
    }
}
