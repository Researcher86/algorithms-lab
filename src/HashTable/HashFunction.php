<?php

declare(strict_types=1);

namespace AlgorithmsLab\HashTable;

use AlgorithmsLab\Foundation\Hashers;
use Closure;

/**
 * A named hash function the tables can share and report.
 *
 * The name is part of the experiment output: "the open-addressing table was
 * slower" is not useful unless the hash function is named as well.
 */
final readonly class HashFunction
{
    /**
     * @param Closure(string): int $hasher
     */
    private function __construct(
        public string $name,
        private Closure $hasher,
    ) {
    }

    public static function fnv1a64(): self
    {
        return new self('fnv1a64', static fn (string $key): int => Hashers::fnv1a64($key));
    }

    public static function xxh128(): self
    {
        return new self('xxh128', static fn (string $key): int => Hashers::xxh128($key));
    }

    public static function crc32(): self
    {
        return new self('crc32', static fn (string $key): int => Hashers::crc32($key));
    }

    public static function murmur3(int $seed = 0): self
    {
        return new self(
            sprintf('murmur3(%d)', $seed),
            static fn (string $key): int => Hashers::murmur3($key, $seed),
        );
    }

    /**
     * @param Closure(string): int $hasher
     */
    public static function of(string $name, Closure $hasher): self
    {
        return new self($name, $hasher);
    }

    public function hash(string $key): int
    {
        return ($this->hasher)($key) & PHP_INT_MAX;
    }
}
