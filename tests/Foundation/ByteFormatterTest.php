<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Foundation;

use AlgorithmsLab\Foundation\ByteFormatter;
use PHPUnit\Framework\TestCase;

final class ByteFormatterTest extends TestCase
{
    public function testFormatsBytesWithoutScaling(): void
    {
        self::assertSame('0 B', ByteFormatter::format(0));
        self::assertSame('1023 B', ByteFormatter::format(1023));
    }

    public function testScalesToBinaryUnits(): void
    {
        self::assertSame('1.0 KiB', ByteFormatter::format(1024));
        self::assertSame('1.5 KiB', ByteFormatter::format(1536));
        self::assertSame('1.0 MiB', ByteFormatter::format(1024 * 1024));
        self::assertSame('1.0 GiB', ByteFormatter::format(1024 * 1024 * 1024));
    }

    public function testFormatsSignedValues(): void
    {
        self::assertSame('+1.0 KiB', ByteFormatter::formatSigned(1024));
        self::assertSame('-1.0 KiB', ByteFormatter::formatSigned(-1024));
        self::assertSame('0 B', ByteFormatter::formatSigned(0));
    }
}
