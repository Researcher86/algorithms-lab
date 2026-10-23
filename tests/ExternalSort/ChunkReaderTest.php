<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\ExternalSort;

use AlgorithmsLab\ExternalSort\ChunkReader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ChunkReaderTest extends TestCase
{
    public function testCutsOnTheApproximateByteLimit(): void
    {
        $reader = new ChunkReader(10);
        $chunks = iterator_to_array($reader->chunks(['123', '45', '6789', '0']), false);

        self::assertSame([['123', '45'], ['6789', '0']], $chunks);
        self::assertSame(10, $reader->chunkBytes());
    }

    public function testKeepsAnOversizedRecordInOneChunk(): void
    {
        $chunks = iterator_to_array(new ChunkReader(3)->chunks(['long-record', 'x']), false);

        self::assertSame([['long-record'], ['x']], $chunks);
    }

    public function testEmptyInputYieldsNoChunks(): void
    {
        self::assertSame([], iterator_to_array(new ChunkReader(10)->chunks([]), false));
    }

    public function testRejectsAnInvalidLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ChunkReader(0);
    }
}
