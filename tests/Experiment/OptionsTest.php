<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Experiment;

use AlgorithmsLab\Experiment\Options;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OptionsTest extends TestCase
{
    public function testProvidesTypedAccessorsAndDefaults(): void
    {
        $options = Options::fromStrings([
            'elements' => '100',
            'capacity' => '16',
            'load-factor' => '0.75',
            'seed' => 'test',
        ]);

        self::assertSame(100, $options->elements(1));
        self::assertSame(16, $options->capacity(1));
        self::assertSame(0.75, $options->loadFactor(0.5));
        self::assertSame('test', $options->seed('default'));
        self::assertSame(1, $options->size(1));
        self::assertSame(1, $options->iterations(1));
        self::assertSame(['elements', 'capacity', 'load-factor', 'seed'], $options->names());
    }

    public function testNoneReturnsAllDefaults(): void
    {
        $options = Options::none();

        self::assertSame([], $options->toArray());
        self::assertSame([], $options->names());
    }

    public function testRejectsUnknownOptions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Options::fromStrings(['unknown' => '1']);
    }

    public function testRejectsNonPositiveIntegerOptions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Options::fromStrings(['elements' => '0']);
    }

    public function testRejectsInvalidLoadFactors(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Options::fromStrings(['load-factor' => '1.5']);
    }

    public function testRejectsEmptySeed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Options::fromStrings(['seed' => '']);
    }
}
