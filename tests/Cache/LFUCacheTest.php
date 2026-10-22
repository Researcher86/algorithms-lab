<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Cache;

use AlgorithmsLab\Cache\LFUCache;
use AlgorithmsLab\Foundation\DeterministicRandom;
use PHPUnit\Framework\TestCase;

final class LFUCacheTest extends TestCase
{
    public function testEvictsTheLeastFrequentEntry(): void
    {
        $cache = new LFUCache(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->get('a');
        $cache->put('c', 3);

        self::assertTrue($cache->has('a'));
        self::assertFalse($cache->has('b'));
        self::assertTrue($cache->has('c'));
        self::assertSame(2, $cache->frequencyOf('a'));
        self::assertSame(1, $cache->frequencyOf('c'));
    }

    public function testTiesAreBrokenByRecency(): void
    {
        $cache = new LFUCache(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->put('c', 3);

        self::assertFalse($cache->has('a'));
        self::assertTrue($cache->has('b'));
        self::assertTrue($cache->has('c'));
    }

    public function testPutExistingPromotesTheEntry(): void
    {
        $cache = new LFUCache(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->put('a', 3);
        $cache->put('c', 4);

        self::assertTrue($cache->has('a'));
        self::assertFalse($cache->has('b'));
        self::assertSame(2, $cache->frequencyOf('a'));
    }

    public function testRemoveUpdatesTheMinimumFrequency(): void
    {
        $cache = new LFUCache(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->get('a');

        self::assertSame(1, $cache->minFrequency());

        $cache->remove('b');

        self::assertSame(2, $cache->minFrequency());
        self::assertSame(1, $cache->count());
    }

    public function testCountersRecordPointerWork(): void
    {
        $cache = new LFUCache(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->get('a');

        self::assertGreaterThan(0, $cache->counters()->relinks);
    }

    public function testMatchesAReferenceLfuModelUnderRandomOperations(): void
    {
        $random = new DeterministicRandom('lfu-differential');
        $capacity = 5;
        $cache = new LFUCache($capacity);
        $clock = 0;

        /** @var array<string, array{value: int, frequency: int, lastUsed: int}> $model */
        $model = [];

        for ($step = 0; $step < 1_000; ++$step) {
            $key = 'key-' . $random->intBetween(0, 12);
            $operation = $random->intBetween(0, 2);

            if ($operation === 0) {
                $value = $random->intBetween(0, 1_000_000);

                if (array_key_exists($key, $model)) {
                    $model[$key]['value'] = $value;
                    ++$model[$key]['frequency'];
                    $model[$key]['lastUsed'] = ++$clock;
                    $cache->put($key, $value);

                    continue;
                }

                if (count($model) >= $capacity) {
                    $victim = null;
                    $victimFrequency = PHP_INT_MAX;
                    $victimUsed = PHP_INT_MAX;

                    foreach ($model as $candidate => $entry) {
                        if (
                            $entry['frequency'] < $victimFrequency
                            || ($entry['frequency'] === $victimFrequency && $entry['lastUsed'] < $victimUsed)
                        ) {
                            $victim = $candidate;
                            $victimFrequency = $entry['frequency'];
                            $victimUsed = $entry['lastUsed'];
                        }
                    }

                    if ($victim !== null) {
                        unset($model[$victim]);
                    }
                }

                $model[$key] = ['value' => $value, 'frequency' => 1, 'lastUsed' => ++$clock];
                $cache->put($key, $value);

                continue;
            }

            if ($operation === 1) {
                if (!array_key_exists($key, $model)) {
                    self::assertNull($cache->get($key));

                    continue;
                }

                ++$model[$key]['frequency'];
                $model[$key]['lastUsed'] = ++$clock;

                self::assertSame($model[$key]['value'], $cache->get($key));

                continue;
            }

            self::assertSame(array_key_exists($key, $model), $cache->remove($key));
            unset($model[$key]);
        }

        self::assertSame(count($model), $cache->count());

        foreach ($model as $key => $entry) {
            self::assertSame($entry['frequency'], $cache->frequencyOf($key));
        }

        $minimum = 0;

        if ($model !== []) {
            $minimum = min(array_column($model, 'frequency'));
        }

        self::assertSame($minimum, $cache->minFrequency());
    }
}
