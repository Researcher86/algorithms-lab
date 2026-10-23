<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\ExternalSort;

use AlgorithmsLab\ExternalSort\LineReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LineReaderTest extends TestCase
{
    public function testReadsLinesWithoutTheirEndings(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'algorithms-lab-lines-');
        self::assertNotFalse($path);
        file_put_contents($path, "alpha\nbeta\r\ngamma");

        try {
            self::assertSame(['alpha', 'beta', 'gamma'], iterator_to_array(LineReader::lines($path), false));
        } finally {
            unlink($path);
        }
    }

    public function testEmptyFileYieldsNothing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'algorithms-lab-lines-');
        self::assertNotFalse($path);

        try {
            self::assertSame([], iterator_to_array(LineReader::lines($path), false));
        } finally {
            unlink($path);
        }
    }

    public function testMissingFileIsAnError(): void
    {
        $this->expectException(RuntimeException::class);

        iterator_to_array(LineReader::lines('/does/not/exist'), false);
    }
}
