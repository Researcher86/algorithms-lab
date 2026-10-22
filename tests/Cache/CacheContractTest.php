<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Cache;

use AlgorithmsLab\Cache\ArrayLRU;
use AlgorithmsLab\Cache\Cache;
use AlgorithmsLab\Cache\HeapLFU;
use AlgorithmsLab\Cache\LFUCache;
use AlgorithmsLab\Cache\LRUCache;
use AlgorithmsLab\Cache\NaiveLRU;
use Closure;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CacheContractTest extends TestCase
{
    /**
     * @return array<string, array{Closure(int): Cache}>
     */
    public static function caches(): array
    {
        return [
            'lru' => [static fn (int $capacity): Cache => new LRUCache($capacity)],
            'lfu' => [static fn (int $capacity): Cache => new LFUCache($capacity)],
            'heap-lfu' => [static fn (int $capacity): Cache => new HeapLFU($capacity)],
            'array' => [static fn (int $capacity): Cache => new ArrayLRU($capacity)],
            'naive' => [static fn (int $capacity): Cache => new NaiveLRU($capacity)],
        ];
    }

    #[DataProvider('caches')]
    public function testStoresReadsUpdatesAndRemoves(Closure $factory): void
    {
        $cache = $factory(2);
        $cache->put('a', 1);
        $cache->put('b', 2);

        self::assertSame(1, $cache->get('a'));
        self::assertSame(2, $cache->get('b'));
        self::assertSame(2, $cache->count());

        $cache->put('a', 3);

        self::assertSame(3, $cache->get('a'));
        self::assertSame(2, $cache->count());
        self::assertTrue($cache->has('b'));
        self::assertTrue($cache->remove('b'));
        self::assertFalse($cache->has('b'));
        self::assertSame(1, $cache->count());
    }

    #[DataProvider('caches')]
    public function testStoredNullIsDistinguishableWithHas(Closure $factory): void
    {
        $cache = $factory(1);
        $cache->put('null', null);

        self::assertTrue($cache->has('null'));
        self::assertNull($cache->get('null'));
        self::assertSame(1, $cache->stats()->hits);
    }

    #[DataProvider('caches')]
    public function testHasDoesNotChangeRecencyOrStats(Closure $factory): void
    {
        $cache = $factory(2);
        $cache->put('a', 1);
        $cache->put('b', 2);

        self::assertTrue($cache->has('a'));
        self::assertSame(0, $cache->stats()->hits);
        self::assertSame(0, $cache->stats()->misses);

        $cache->put('c', 3);

        self::assertFalse($cache->has('a'));
        self::assertTrue($cache->has('b'));
        self::assertTrue($cache->has('c'));
    }

    #[DataProvider('caches')]
    public function testEvictsAtCapacity(Closure $factory): void
    {
        $cache = $factory(1);
        $cache->put('a', 1);
        $cache->put('b', 2);

        self::assertSame(1, $cache->count());
        self::assertFalse($cache->has('a'));
        self::assertSame(2, $cache->get('b'));
        self::assertSame(1, $cache->stats()->evictions);
    }

    #[DataProvider('caches')]
    public function testCapacityZeroStoresNothing(Closure $factory): void
    {
        $cache = $factory(0);
        $cache->put('a', 1);

        self::assertSame(0, $cache->count());
        self::assertFalse($cache->has('a'));
        self::assertNull($cache->get('a'));
        self::assertSame(1, $cache->stats()->misses);
    }

    #[DataProvider('caches')]
    public function testGetMissIsCounted(Closure $factory): void
    {
        $cache = $factory(2);

        self::assertNull($cache->get('missing'));
        self::assertSame(0, $cache->stats()->hits);
        self::assertSame(1, $cache->stats()->misses);
    }

    #[DataProvider('caches')]
    public function testRemoveMissingReturnsFalse(Closure $factory): void
    {
        self::assertFalse($factory(2)->remove('missing'));
    }

    #[DataProvider('caches')]
    public function testClearResetsEntriesAndStats(Closure $factory): void
    {
        $cache = $factory(2);
        $cache->put('a', 1);
        $cache->get('a');
        $cache->get('missing');
        $cache->clear();

        self::assertSame(0, $cache->count());
        self::assertFalse($cache->has('a'));
        self::assertSame(0, $cache->stats()->hits);
        self::assertSame(0, $cache->stats()->misses);
        self::assertSame(0, $cache->stats()->evictions);
        self::assertSame(0, $cache->counters()->relinks);
        self::assertSame(0, $cache->counters()->comparisons);
    }

    #[DataProvider('caches')]
    public function testRejectsNegativeCapacity(Closure $factory): void
    {
        $this->expectException(InvalidArgumentException::class);

        $factory(-1);
    }
}
