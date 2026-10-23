<?php

declare(strict_types=1);

namespace AlgorithmsLab\ExternalSort;

use Generator;
use RuntimeException;

/**
 * Lazily reads newline-delimited records from a file.
 *
 * A line is yielded without its line ending; the reader does not materialize
 * the file. The generator's finally block closes the handle even when the
 * caller stops consuming early.
 */
final class LineReader
{
    /**
     * @return Generator<int, string>
     */
    public static function lines(string $path): Generator
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Cannot open input file: %s', $path));
        }

        try {
            while (($line = fgets($handle)) !== false) {
                yield self::stripLineEnding($line);
            }
        } finally {
            fclose($handle);
        }
    }

    private static function stripLineEnding(string $line): string
    {
        if (str_ends_with($line, "\n")) {
            $line = substr($line, 0, -1);
        }

        if (str_ends_with($line, "\r")) {
            $line = substr($line, 0, -1);
        }

        return $line;
    }
}
