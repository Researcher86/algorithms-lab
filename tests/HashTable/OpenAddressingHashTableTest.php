<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\HashTable;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\HashTable\OpenAddressingHashTable;
use AlgorithmsLab\HashTable\ProbeStrategy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OpenAddressingHashTableTest extends TestCase
{
    /**
     * @return array<string, array{ProbeStrategy}>
     */
    public static function strategies(): array
    {
        return [
            'linear' => [ProbeStrategy::Linear],
            'quadratic' => [ProbeStrategy::Quadratic],
            'double' => [ProbeStrategy::DoubleHashing],
        ];
    }

    #[DataProvider('strategies')]
    public function testStoresUpdatesAndRemovesValues(ProbeStrategy $strategy): void
    {
        $table = new OpenAddressingHashTable(strategy: $strategy);
        $table->put('a', 1);
        $table->put('b', 2);

        self::assertSame(1, $table->get('a'));
        self::assertSame(2, $table->get('b'));
        self::assertSame(2, $table->count());

        $table->put('a', 3);

        self::assertSame(3, $table->get('a'));
        self::assertSame(2, $table->count());
        self::assertTrue($table->remove('b'));
        self::assertFalse($table->has('b'));
        self::assertSame(1, $table->count());
        self::assertGreaterThan(0.0, $table->tombstoneRatio());
    }

    #[DataProvider('strategies')]
    public function testMatchesAPlainArrayUnderRandomOperations(ProbeStrategy $strategy): void
    {
        $random = new DeterministicRandom('open-differential-' . $strategy->value);
        $table = new OpenAddressingHashTable(
            capacity: 8,
            strategy: $strategy,
            maxLoadFactor: 0.7,
        );
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

    #[DataProvider('strategies')]
    public function testGrowsAndKeepsEveryEntry(ProbeStrategy $strategy): void
    {
        $table = new OpenAddressingHashTable(
            capacity: 4,
            strategy: $strategy,
            maxLoadFactor: 0.75,
        );

        for ($i = 0; $i < 1000; ++$i) {
            $table->put('key-' . $i, $i);
        }

        self::assertSame(1000, $table->count());
        self::assertGreaterThan(4, $table->capacity());
        self::assertGreaterThan(0, $table->counters()->rehashes);

        for ($i = 0; $i < 1000; ++$i) {
            self::assertSame($i, $table->get('key-' . $i));
        }
    }

    public function testReusesTombstones(): void
    {
        $table = new OpenAddressingHashTable(capacity: 8, maxLoadFactor: 0.9);
        $table->put('a', 1);
        $table->put('b', 2);
        $table->remove('a');

        self::assertFalse($table->has('a'));
        self::assertTrue($table->has('b'));

        $table->put('c', 3);

        self::assertSame(3, $table->get('c'));
        self::assertSame(2, $table->count());
    }

    public function testCapacityIsRoundedUpToPowerOfTwo(): void
    {
        $table = new OpenAddressingHashTable(capacity: 10);

        self::assertSame(16, $table->capacity());
    }

    public function testCountersRecordProbesAndComparisons(): void
    {
        $table = new OpenAddressingHashTable();
        $table->put('key', 1);
        $table->get('key');
        $table->remove('missing');

        self::assertGreaterThan(0, $table->counters()->hashCalls);
        self::assertGreaterThan(0, $table->counters()->probes);
        self::assertGreaterThan(0, $table->counters()->comparisons);
    }

    public function testClearResetsTheTable(): void
    {
        $table = new OpenAddressingHashTable();
        $table->put('key', 1);
        $table->remove('key');
        $table->clear();

        self::assertSame(0, $table->count());
        self::assertSame(0.0, $table->tombstoneRatio());
        self::assertSame(0, $table->counters()->hashCalls);
    }

    public function testRejectsInvalidConstructionParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OpenAddressingHashTable(capacity: 0);
    }

    public function testRejectsAnInvalidLoadFactor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OpenAddressingHashTable(maxLoadFactor: 1.1);
    }
}
