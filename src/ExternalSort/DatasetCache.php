<?php

declare(strict_types=1);

namespace AlgorithmsLab\ExternalSort;

use InvalidArgumentException;

/**
 * Generates a deterministic input file once and reuses it across experiments.
 *
 * The file name includes the pattern, record count and a hash of the seed.
 * Reuse is safe only when the size matches; a truncated or partially written
 * file is regenerated instead of silently producing a different workload.
 */
final class DatasetCache
{
    /**
     * @return array{path: string, records: int, bytes: int}
     */
    public static function ensure(string $directory, string $pattern, int $targetBytes, string $seed): array
    {
        if ($targetBytes < DatasetGenerator::recordBytes()) {
            throw new InvalidArgumentException('External-sort input must hold at least one record');
        }

        $records = DatasetGenerator::recordsForBytes($targetBytes);
        $bytes = $records * DatasetGenerator::recordBytes();
        $name = sprintf(
            'external-sort-%s-%d-%s.txt',
            $pattern,
            $records,
            substr(hash('xxh128', $seed), 0, 12),
        );
        $path = rtrim($directory, '/') . '/' . $name;

        if (is_file($path) && filesize($path) === $bytes) {
            return ['path' => $path, 'records' => $records, 'bytes' => $bytes];
        }

        $info = DatasetGenerator::writeFile($path, $pattern, $records, $seed);

        return ['path' => $path, 'records' => $info['records'], 'bytes' => $info['bytes']];
    }
}
