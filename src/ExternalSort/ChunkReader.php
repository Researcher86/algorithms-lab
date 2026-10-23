<?php

declare(strict_types=1);

namespace AlgorithmsLab\ExternalSort;

use Generator;
use InvalidArgumentException;

/**
 * Groups an iterable of records into chunks by approximate byte size.
 *
 * The size is the record length plus one for its newline. A chunk is yielded
 * before adding a record that would cross the limit, so a chunk can exceed the
 * limit only when a single record is larger than the requested chunk. That is
 * deliberate: an oversized record must still have somewhere to go.
 */
final class ChunkReader
{
    public function __construct(
        private readonly int $chunkBytes,
    ) {
        if ($chunkBytes < 1) {
            throw new InvalidArgumentException('Chunk size must be at least 1 byte');
        }
    }

    /**
     * @param iterable<string> $records
     * @return Generator<int, list<string>>
     */
    public function chunks(iterable $records): Generator
    {
        $chunk = [];
        $bytes = 0;

        foreach ($records as $record) {
            $size = strlen($record) + 1;

            if ($chunk !== [] && $bytes + $size > $this->chunkBytes) {
                yield $chunk;
                $chunk = [];
                $bytes = 0;
            }

            $chunk[] = $record;
            $bytes += $size;
        }

        if ($chunk !== []) {
            yield $chunk;
        }
    }

    public function chunkBytes(): int
    {
        return $this->chunkBytes;
    }
}
