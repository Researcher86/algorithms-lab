<?php

declare(strict_types=1);

namespace AlgorithmsLab\Foundation;

use InvalidArgumentException;
use OutOfRangeException;

/**
 * A fixed-size bit array backed by a PHP string.
 *
 * This is the storage primitive Bloom filters and visited-sets use. It is
 * small on purpose: one byte per eight bits, no object per bit, and no magic
 * beyond the shift arithmetic.
 */
final class BitArray
{
    private string $bytes;

    public function __construct(
        private readonly int $size,
    ) {
        if ($size < 0) {
            throw new InvalidArgumentException('Bit array size cannot be negative');
        }

        $this->bytes = str_repeat("\0", intdiv($size + 7, 8));
    }

    public function size(): int
    {
        return $this->size;
    }

    public function set(int $index, bool $value = true): void
    {
        $this->assertIndex($index);
        $byte = intdiv($index, 8);
        $mask = 1 << ($index % 8);
        $current = ord($this->bytes[$byte]);

        $this->bytes[$byte] = chr($value ? ($current | $mask) & 0xFF : $current & ~$mask & 0xFF);
    }

    public function get(int $index): bool
    {
        $this->assertIndex($index);
        $byte = intdiv($index, 8);
        $mask = 1 << ($index % 8);

        return (ord($this->bytes[$byte]) & $mask) !== 0;
    }

    public function popcount(): int
    {
        $count = 0;

        for ($i = 0, $length = strlen($this->bytes); $i < $length; ++$i) {
            $byte = ord($this->bytes[$i]);

            while ($byte !== 0) {
                $count += $byte & 1;
                $byte >>= 1;
            }
        }

        return $count;
    }

    public function clear(): void
    {
        $this->bytes = str_repeat("\0", strlen($this->bytes));
    }

    /**
     * The raw bytes, useful when a Bloom filter needs to persist its bits.
     */
    public function toBytes(): string
    {
        return $this->bytes;
    }

    private function assertIndex(int $index): void
    {
        if ($index < 0 || $index >= $this->size) {
            throw new OutOfRangeException(sprintf('Bit index %d is outside [0, %d)', $index, $this->size));
        }
    }
}
