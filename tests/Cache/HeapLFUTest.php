<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Cache;

use AlgorithmsLab\Cache\HeapLFU;
use AlgorithmsLab\Foundation\DeterministicRandom;
use PHPUnit\Framework\TestCase;

final class HeapLFUTest extends TestCase
{
    public function testEvictsTheLeastFrequentEntry(): void
    {
        $cache = new HeapLFU(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->get('a');
        $cache->put('c', 3);

        self::assertTrue($cache->has('a'));
        self::assertFalse($cache->has('b'));
        self::assertTrue($cache->has('c'));
        self::assertSame(2, $cache->frequencyOf('a'));
    }

    public function testTiesAreBrokenByRecency(): void
    {
        $cache = new HeapLFU(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->put('c', 3);

        self::assertFalse($cache->has('a'));
        self::assertTrue($cache->has('b'));
        self::assertTrue($cache->has('c'));
    }

    public function testRemovedEntriesBecomeStaleHeapRecords(): void
    {
        $cache = new HeapLFU(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->remove('a');
        $cache->put('c', 3);

        self::assertTrue($cache->has('b'));
        self::assertTrue($cache->has('c'));
        self::assertSame(2, $cache->count());
    }

    public function testCountsPoppedHeapRecords(): void
    {
        $cache = new HeapLFU(1);
        $cache->put('a', 1);
        $cache->get('a');
        $cache->get('a');
        $cache->put('b', 2);

        self::assertGreaterThan(0, $cache->counters()->nodeReads);
    }

    public function testMatchesAReferenceLfuModelUnderRandomOperations(): void
    {
        $random = new DeterministicRandom('heap-lfu-differential');
        $capacity = 5;
        $cache = new HeapLFU($capacity);
        $clock = 0;

        /** @var array<string, array{value: int, frequency: int, lastUsed: int}> $model */
        $model = [];

        for ($step = 0; $step < 500; ++$step) {
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
    }
}
