<?php

declare(strict_types=1);

namespace AlgorithmsLab\Foundation;

use RuntimeException;

/**
 * The hash functions used by the structures in this lab.
 *
 * FNV-1a and xxh128 are provided by ext-hash. Murmur3 is implemented here so
 * that a chapter can compare how a different mixing strategy changes probe
 * counts; the implementation is the x86 32-bit variant published by Appleby.
 *
 * The 64-bit functions return the low 60 bits so the result always fits in a
 * signed PHP integer without becoming a float. That is enough for a bucket
 * index and is stated here rather than left to a cast somewhere downstream.
 */
final class Hashers
{
    private const LOW_60_BITS_HEX_DIGITS = 15;

    public static function fnv1a64(string $key): int
    {
        return self::low60(hash('fnv1a64', $key));
    }

    public static function xxh128(string $key): int
    {
        return self::low60(hash('xxh128', $key));
    }

    public static function crc32(string $key): int
    {
        return crc32($key) & 0xFFFFFFFF;
    }

    public static function murmur3(string $key, int $seed = 0): int
    {
        $c1 = 0xCC9E2D51;
        $c2 = 0x1B873593;
        $hash = $seed & 0xFFFFFFFF;
        $length = strlen($key);
        $blocks = intdiv($length, 4);

        for ($i = 0; $i < $blocks; ++$i) {
            $unpacked = unpack('Vvalue', substr($key, $i * 4, 4));

            if ($unpacked === false) {
                throw new RuntimeException('Unable to unpack a Murmur3 block');
            }

            $value = (int) $unpacked['value'];
            $value = self::mul32($value, $c1);
            $value = self::rotateLeft32($value, 15);
            $value = self::mul32($value, $c2);
            $hash ^= $value;
            $hash = self::rotateLeft32($hash, 13);
            $hash = self::add32(self::mul32($hash, 5), 0xE6546B64);
        }

        $tail = $length & 3;
        $offset = $blocks * 4;
        $value = 0;

        if ($tail === 3) {
            $value ^= ord($key[$offset + 2]) << 16;
        }

        if ($tail >= 2) {
            $value ^= ord($key[$offset + 1]) << 8;
        }

        if ($tail >= 1) {
            $value ^= ord($key[$offset]);
            $value = self::mul32($value, $c1);
            $value = self::rotateLeft32($value, 15);
            $value = self::mul32($value, $c2);
            $hash ^= $value;
        }

        $hash ^= $length;
        $hash ^= $hash >> 16;
        $hash = self::mul32($hash, 0x85EBCA6B);
        $hash ^= $hash >> 13;
        $hash = self::mul32($hash, 0xC2B2AE35);
        $hash ^= $hash >> 16;

        return $hash & 0xFFFFFFFF;
    }

    private static function low60(string $hex): int
    {
        return (int) hexdec(substr($hex, 0, self::LOW_60_BITS_HEX_DIGITS));
    }

    private static function mul32(int $left, int $right): int
    {
        $left &= 0xFFFFFFFF;
        $right &= 0xFFFFFFFF;
        $low = $left & 0xFFFF;
        $high = ($left >> 16) & 0xFFFF;

        return (($low * $right) + ((($high * $right) & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }

    private static function add32(int $left, int $right): int
    {
        return ($left + $right) & 0xFFFFFFFF;
    }

    private static function rotateLeft32(int $value, int $shift): int
    {
        return (($value << $shift) | ($value >> (32 - $shift))) & 0xFFFFFFFF;
    }
}
