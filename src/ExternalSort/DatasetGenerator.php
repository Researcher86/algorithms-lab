<?php

declare(strict_types=1);

namespace AlgorithmsLab\ExternalSort;

use AlgorithmsLab\Foundation\DeterministicRandom;
use Closure;
use Generator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Writes newline-delimited records with a leading fixed-width numeric key.
 *
 * The payload makes records long enough that a 100 MB input is realistic
 * without requiring hundreds of millions of records. The key is part of the
 * record so an experiment can compare by key only, which exposes stability for
 * equal keys while the payload still differs.
 */
final class DatasetGenerator
{
    public const KEY_WIDTH = 10;
    public const PAYLOAD_BYTES = 64;

    /**
     * @return list<string>
     */
    public static function patterns(): array
    {
        return ['random', 'sorted', 'reversed', 'duplicate'];
    }

    public static function recordBytes(): int
    {
        return self::KEY_WIDTH + 1 + self::PAYLOAD_BYTES + 1;
    }

    /**
     * @return Closure(string, string): int
     */
    public static function keyComparator(): Closure
    {
        return static fn (string $left, string $right): int => strcmp(
            substr($left, 0, self::KEY_WIDTH),
            substr($right, 0, self::KEY_WIDTH),
        );
    }

    public static function recordsForBytes(int $bytes): int
    {
        if ($bytes < 0) {
            throw new InvalidArgumentException('Target bytes cannot be negative');
        }

        return intdiv($bytes, self::recordBytes());
    }

    /**
     * @return Generator<int, string>
     */
    public static function records(string $pattern, int $records, string $seed = 'book'): Generator
    {
        if (!in_array($pattern, self::patterns(), true)) {
            throw new InvalidArgumentException(sprintf('Unknown dataset pattern: %s', $pattern));
        }

        if ($records < 0) {
            throw new InvalidArgumentException('Record count cannot be negative');
        }

        $random = new DeterministicRandom($seed . ':' . $pattern);

        for ($index = 0; $index < $records; ++$index) {
            $key = match ($pattern) {
                'sorted' => $index,
                'reversed' => $records - $index - 1,
                'duplicate' => $random->intBetween(0, max(1, intdiv($records, 100))),
                default => $random->intBetween(0, max(1, $records * 10)),
            };
            $payload = str_pad('record-' . $index, self::PAYLOAD_BYTES, '.', STR_PAD_RIGHT);
            yield sprintf('%0' . self::KEY_WIDTH . 'd %s', $key, $payload);
        }
    }

    /**
     * @return array{records: int, bytes: int}
     */
    public static function writeFile(string $path, string $pattern, int $records, string $seed = 'book'): array
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Cannot create dataset directory: %s', $directory));
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Cannot write dataset: %s', $path));
        }

        $buffer = [];
        $bytes = 0;

        try {
            foreach (self::records($pattern, $records, $seed) as $record) {
                $buffer[] = $record;
                $bytes += strlen($record) + 1;

                if (count($buffer) >= 1_024) {
                    self::writeBuffer($handle, $buffer);
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                self::writeBuffer($handle, $buffer);
            }
        } finally {
            fclose($handle);
        }

        return ['records' => $records, 'bytes' => $bytes];
    }

    /**
     * @param resource $handle
     * @param list<string> $buffer
     */
    private static function writeBuffer($handle, array $buffer): void
    {
        $data = implode("\n", $buffer) . "\n";

        if (fwrite($handle, $data) !== strlen($data)) {
            throw new RuntimeException('Could not write the dataset buffer');
        }
    }
}
