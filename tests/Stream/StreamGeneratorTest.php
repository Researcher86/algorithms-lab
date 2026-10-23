<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Stream;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Stream\StreamGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StreamGeneratorTest extends TestCase
{
    public function testUniformStreamHasTheRequestedLengthAndAlphabet(): void
    {
        $stream = StreamGenerator::uniform(100, 5, new DeterministicRandom('uniform'));

        self::assertCount(100, $stream);

        foreach ($stream as $key) {
            self::assertMatchesRegularExpression('/^item-[0-4]$/', $key);
        }
    }

    public function testZipfStreamIsDeterministicAndUsesRanks(): void
    {
        $first = StreamGenerator::zipf(50, 10, 1.1, new DeterministicRandom('zipf'));
        $second = StreamGenerator::zipf(50, 10, 1.1, new DeterministicRandom('zipf'));

        self::assertSame($first, $second);
        self::assertCount(50, $first);
        self::assertMatchesRegularExpression('/^item-\\d+$/', $first[0]);
    }

    public function testAllUniqueStreamHasNoDuplicates(): void
    {
        $stream = StreamGenerator::allUnique(100);

        self::assertCount(100, $stream);
        self::assertCount(100, array_unique($stream));
        self::assertSame('unique-0', $stream[0]);
        self::assertSame('unique-99', $stream[99]);
    }

    public function testDriftSwitchesTheHotSet(): void
    {
        $stream = StreamGenerator::drift(100, 100, new DeterministicRandom('drift'));

        self::assertCount(100, $stream);

        foreach (array_slice($stream, 0, 50) as $key) {
            self::assertStringStartsWith('phase-a-', $key);
        }

        foreach (array_slice($stream, 50) as $key) {
            self::assertStringStartsWith('phase-b-', $key);
        }
    }

    public function testRejectsInvalidLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StreamGenerator::uniform(-1, 10, new DeterministicRandom('bad'));
    }

    public function testRejectsInvalidAlphabet(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StreamGenerator::uniform(10, 0, new DeterministicRandom('bad'));
    }
}
