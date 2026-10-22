<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Foundation;

use AlgorithmsLab\Foundation\Hashers;
use PHPUnit\Framework\TestCase;

final class HashersTest extends TestCase
{
    public function testFnv1a64MatchesTheReferenceHashPrefix(): void
    {
        $expected = (int) hexdec(substr(hash('fnv1a64', 'hello'), 0, 15));

        self::assertSame($expected, Hashers::fnv1a64('hello'));
    }

    public function testXxh128MatchesTheReferenceHashPrefix(): void
    {
        $expected = (int) hexdec(substr(hash('xxh128', 'hello'), 0, 15));

        self::assertSame($expected, Hashers::xxh128('hello'));
    }

    public function testCrc32MatchesTheBuiltInFunction(): void
    {
        self::assertSame(crc32('hello') & 0xFFFFFFFF, Hashers::crc32('hello'));
    }

    public function testMurmur3MatchesKnownVectors(): void
    {
        self::assertSame(0, Hashers::murmur3(''));
        self::assertSame(0x248BFA47, Hashers::murmur3('hello'));
    }

    public function testMurmur3SeedChangesTheResult(): void
    {
        self::assertNotSame(Hashers::murmur3('hello', 1), Hashers::murmur3('hello', 2));
    }

    public function testHashesStayInsideTheirValueSpaces(): void
    {
        foreach (['', 'a', 'hello', 'a longer key with spaces'] as $key) {
            self::assertGreaterThanOrEqual(0, Hashers::fnv1a64($key));
            self::assertGreaterThanOrEqual(0, Hashers::xxh128($key));
            self::assertGreaterThanOrEqual(0, Hashers::crc32($key));
            self::assertGreaterThanOrEqual(0, Hashers::murmur3($key));
            self::assertLessThanOrEqual(0xFFFFFFFF, Hashers::crc32($key));
            self::assertLessThanOrEqual(0xFFFFFFFF, Hashers::murmur3($key));
        }
    }
}
