<?php

declare(strict_types=1);

namespace AlgorithmsLab\Stream;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Foundation\ZipfDistribution;
use InvalidArgumentException;

/**
 * Reproducible synthetic streams for the chapter's experiments.
 *
 * Keys are strings so they exercise the same path as real identifiers. The
 * generator materializes the stream because the experiments replay it through
 * several algorithms; a production stream would hand out a generator instead.
 */
final class StreamGenerator
{
    /**
     * @return list<string>
     */
    public static function uniform(int $length, int $alphabet, DeterministicRandom $random): array
    {
        self::assertLength($length);
        self::assertAlphabet($alphabet, 1);
        $stream = [];

        for ($index = 0; $index < $length; ++$index) {
            $stream[] = 'item-' . $random->intBetween(0, $alphabet - 1);
        }

        return $stream;
    }

    /**
     * @return list<string>
     */
    public static function zipf(int $length, int $items, float $alpha, DeterministicRandom $random): array
    {
        self::assertLength($length);
        self::assertAlphabet($items, 1);

        if ($alpha < 0.0) {
            throw new InvalidArgumentException('Zipf alpha cannot be negative');
        }

        $distribution = new ZipfDistribution($items, $alpha, $random);
        $stream = [];

        for ($index = 0; $index < $length; ++$index) {
            $stream[] = 'item-' . ($distribution->next() - 1);
        }

        return $stream;
    }

    /**
     * @return list<string>
     */
    public static function allUnique(int $length): array
    {
        self::assertLength($length);
        $stream = [];

        for ($index = 0; $index < $length; ++$index) {
            $stream[] = 'unique-' . $index;
        }

        return $stream;
    }

    /**
     * First half repeats a hot set, second half repeats a disjoint hot set.
     *
     * The alphabet is split so the drift is visible without making the two
     * phases overlap by accident. A small alphabet gives fewer keys per phase;
     * the hot-set size is therefore capped at one tenth of the alphabet.
     *
     * @return list<string>
     */
    public static function drift(int $length, int $alphabet, DeterministicRandom $random): array
    {
        self::assertLength($length);
        self::assertAlphabet($alphabet, 2);
        $hot = max(1, intdiv($alphabet, 10));
        $hot = min($hot, intdiv($alphabet, 2));
        $half = intdiv($length, 2);
        $stream = [];

        for ($index = 0; $index < $half; ++$index) {
            $stream[] = 'phase-a-' . $random->intBetween(0, $hot - 1);
        }

        for ($index = $half; $index < $length; ++$index) {
            $stream[] = 'phase-b-' . ($hot + $random->intBetween(0, $hot - 1));
        }

        return $stream;
    }

    private static function assertLength(int $length): void
    {
        if ($length < 0) {
            throw new InvalidArgumentException('Stream length cannot be negative');
        }
    }

    private static function assertAlphabet(int $alphabet, int $minimum): void
    {
        if ($alphabet < $minimum) {
            throw new InvalidArgumentException(sprintf('Alphabet must be at least %d', $minimum));
        }
    }
}
