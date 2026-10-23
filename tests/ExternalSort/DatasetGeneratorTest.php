<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\ExternalSort;

use AlgorithmsLab\ExternalSort\DatasetGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DatasetGeneratorTest extends TestCase
{
    public function testRecordShapeIsFixedWidth(): void
    {
        $records = iterator_to_array(DatasetGenerator::records('random', 100, 'dataset'), false);

        self::assertCount(100, $records);

        foreach ($records as $record) {
            self::assertSame(DatasetGenerator::recordBytes() - 1, strlen($record));
            self::assertMatchesRegularExpression('/^\d{10} record-\d+\.+$/', $record);
        }
    }

    public function testSortedAndReversedPatternsHaveKnownKeys(): void
    {
        $sorted = iterator_to_array(DatasetGenerator::records('sorted', 3), false);
        $reversed = iterator_to_array(DatasetGenerator::records('reversed', 3), false);

        self::assertSame('0000000000', substr($sorted[0], 0, 10));
        self::assertSame('0000000002', substr($sorted[2], 0, 10));
        self::assertSame('0000000002', substr($reversed[0], 0, 10));
        self::assertSame('0000000000', substr($reversed[2], 0, 10));
    }

    public function testWritesAFullFileAndReportsItsBytes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'algorithms-lab-dataset-');
        self::assertNotFalse($path);
        $info = DatasetGenerator::writeFile($path, 'duplicate', 50, 'dataset');

        try {
            self::assertSame(50, $info['records']);
            $lines = file($path);

            if ($lines === false) {
                self::fail('Could not read the generated dataset');
            }

            self::assertCount(50, $lines);
            self::assertSame($info['bytes'], filesize($path));
            self::assertGreaterThan(50, $info['bytes']);
        } finally {
            unlink($path);
        }
    }

    public function testByteTargetUsesTheFixedRecordSize(): void
    {
        self::assertSame(10, DatasetGenerator::recordsForBytes(DatasetGenerator::recordBytes() * 10));
        self::assertSame(0, DatasetGenerator::recordsForBytes(1));
    }

    public function testUnknownPatternIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        iterator_to_array(DatasetGenerator::records('shuffled', 1), false);
    }
}
