<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Cli;

use AlgorithmsLab\Cli\Arguments;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ArgumentsTest extends TestCase
{
    public function testParsesCommandAndOptions(): void
    {
        $arguments = Arguments::parse(['demo:smoke', '--elements=100', '--seed=test']);

        self::assertSame('demo:smoke', $arguments->command);
        self::assertSame('100', $arguments->string('elements', '0'));
        self::assertSame('test', $arguments->string('seed', 'default'));
        self::assertTrue($arguments->has('seed'));
        self::assertFalse($arguments->has('format'));
    }

    public function testParsesIntegerOptions(): void
    {
        $arguments = Arguments::parse(['demo', '--iterations=3', '--warmups=0']);

        self::assertSame(3, $arguments->positiveInt('iterations', 1));
        self::assertSame(0, $arguments->nonNegativeInt('warmups', 1));
        self::assertSame(2, $arguments->positiveInt('missing', 2));
    }

    public function testExceptKeepsOnlyTheRequestedOptions(): void
    {
        $arguments = Arguments::parse(['demo', '--format=json', '--elements=10']);
        $rest = $arguments->except('format');

        self::assertSame(['elements' => '10'], $rest);
    }

    public function testRejectsASecondCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Arguments::parse(['first', 'second']);
    }

    public function testRejectsBareFlags(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Arguments::parse(['--verbose']);
    }

    public function testRejectsInvalidInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Arguments::parse(['demo', '--iterations=0'])->positiveInt('iterations', 1);
    }

    public function testRejectsNegativeWarmups(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Arguments::parse(['demo', '--warmups=-1'])->nonNegativeInt('warmups', 1);
    }
}
