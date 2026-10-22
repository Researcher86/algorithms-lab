<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\HashTable;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\HashTable\ChainingHashTable;
use AlgorithmsLab\HashTable\HashFunction;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ChainingHashTableTest extends TestCase
{
    public function testStoresUpdatesAndRemovesValues(): void
    {
        $table = new ChainingHashTable();
        $table->put('a', 1);
        $table->put('b', 2);

        self::assertSame(1, $table->get('a'));
        self::assertSame(2, $table->get('b'));
        self::assertTrue($table->has('a'));
        self::assertSame(2, $table->count());

        $table->put('a', 3);

        self::assertSame(3, $table->get('a'));
        self::assertSame(2, $table->count());
        self::assertTrue($table->remove('b'));
        self::assertFalse($table->has('b'));
        self::assertNull($table->get('b'));
        self::assertSame(1, $table->count());
    }

    public function testStoredNullIsDistinguishableWithHas(): void
    {
        $table = new ChainingHashTable();
        $table->put('null', null);

        self::assertTrue($table->has('null'));
        self::assertNull($table->get('null'));
        self::assertTrue($table->remove('null'));
    }

    public function testGrowsAndKeepsEveryEntry(): void
    {
        $table = new ChainingHashTable(capacity: 4, maxLoadFactor: 0.75);

        for ($i = 0; $i < 100; ++$i) {
            $table->put('key-' . $i, $i);
        }

        self::assertSame(100, $table->count());
        self::assertGreaterThan(4, $table->capacity());
        self::assertGreaterThan(0, $table->counters()->rehashes);

        for ($i = 0; $i < 100; ++$i) {
            self::assertSame($i, $table->get('key-' . $i));
        }
    }

    public function testCollisionsAndBucketLengthsAreObservable(): void
    {
        $zero = HashFunction::of('zero', static fn (string $key): int => 0);
        $table = new ChainingHashTable(capacity: 4, hashFunction: $zero, maxLoadFactor: 1.0);

        $table->put('a', 1);
        $table->put('b', 2);
        $table->put('c', 3);

        self::assertSame(3, $table->maxBucketLength());
        self::assertSame([3, 0, 0, 0], $table->bucketLengths());
        self::assertSame(2, $table->counters()->collisions);
    }

    public function testFixedCapacityCanBeKeptForDistributionExperiments(): void
    {
        $zero = HashFunction::of('zero', static fn (string $key): int => 0);
        $table = new ChainingHashTable(
            capacity: 4,
            hashFunction: $zero,
            maxLoadFactor: 1.0,
            autoResize: false,
        );

        for ($i = 0; $i < 10; ++$i) {
            $table->put('key-' . $i, $i);
        }

        self::assertSame(4, $table->capacity());
        self::assertSame(10, $table->maxBucketLength());
    }

    public function testMatchesAPlainArrayUnderRandomOperations(): void
    {
        $random = new DeterministicRandom('chaining-differential');
        $table = new ChainingHashTable(capacity: 8);
        $model = [];

        for ($step = 0; $step < 1000; ++$step) {
            $key = 'key-' . $random->intBetween(0, 100);
            $operation = $random->intBetween(0, 2);

            if ($operation === 0) {
                $value = $random->intBetween(0, 1_000_000);
                $table->put($key, $value);
                $model[$key] = $value;

                continue;
            }

            if ($operation === 1) {
                self::assertSame(array_key_exists($key, $model), $table->remove($key));
                unset($model[$key]);

                continue;
            }

            self::assertSame(array_key_exists($key, $model), $table->has($key));
            self::assertSame($model[$key] ?? null, $table->get($key));
        }

        self::assertSame(count($model), $table->count());
    }

    public function testCountersRecordHashCallsProbesAndComparisons(): void
    {
        $table = new ChainingHashTable();
        $table->put('key', 1);
        $table->get('key');
        $table->remove('missing');

        self::assertGreaterThan(0, $table->counters()->hashCalls);
        self::assertGreaterThan(0, $table->counters()->probes);
        self::assertGreaterThan(0, $table->counters()->comparisons);
    }

    public function testClearRemovesEntriesAndResetsCounters(): void
    {
        $table = new ChainingHashTable();
        $table->put('key', 1);
        $table->clear();

        self::assertSame(0, $table->count());
        self::assertSame(0, $table->counters()->hashCalls);
        self::assertFalse($table->has('key'));
    }

    public function testRejectsASmallerCapacityThanTheEntryCount(): void
    {
        $table = new ChainingHashTable();
        $table->put('key', 1);

        $this->expectException(InvalidArgumentException::class);

        $table->resize(0);
    }

    public function testRejectsInvalidConstructionParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ChainingHashTable(capacity: 0);
    }

    public function testRejectsAnInvalidLoadFactor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ChainingHashTable(maxLoadFactor: 0.0);
    }
}
