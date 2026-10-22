<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\HashTable;

use AlgorithmsLab\Foundation\Hashers;
use AlgorithmsLab\HashTable\HashFunction;
use PHPUnit\Framework\TestCase;

final class HashFunctionTest extends TestCase
{
    public function testNamedFunctionsMatchTheFoundationHashers(): void
    {
        self::assertSame(Hashers::fnv1a64('key'), HashFunction::fnv1a64()->hash('key'));
        self::assertSame(Hashers::xxh128('key'), HashFunction::xxh128()->hash('key'));
        self::assertSame(Hashers::crc32('key'), HashFunction::crc32()->hash('key'));
        self::assertSame(Hashers::murmur3('key', 7), HashFunction::murmur3(7)->hash('key'));
    }

    public function testNamesAreReportable(): void
    {
        self::assertSame('fnv1a64', HashFunction::fnv1a64()->name);
        self::assertSame('xxh128', HashFunction::xxh128()->name);
        self::assertSame('crc32', HashFunction::crc32()->name);
        self::assertSame('murmur3(7)', HashFunction::murmur3(7)->name);
    }

    public function testCustomFunctionIsMaskedToANonNegativeInteger(): void
    {
        $hash = HashFunction::of('negative', static fn (string $key): int => -42);

        self::assertGreaterThanOrEqual(0, $hash->hash('key'));
    }
}
