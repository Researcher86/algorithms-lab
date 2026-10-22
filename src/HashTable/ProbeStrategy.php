<?php

declare(strict_types=1);

namespace AlgorithmsLab\HashTable;

/**
 * How an open-addressing table chooses the next slot.
 *
 * Linear probing is the cache-friendly baseline; quadratic probing reduces
 * primary clustering at the price of coverage; double hashing spreads keys
 * with a second step. The table always keeps its capacity a power of two so
 * the double-hash step can be odd and therefore visit every slot.
 */
enum ProbeStrategy: string
{
    case Linear = 'linear';
    case Quadratic = 'quadratic';
    case DoubleHashing = 'double';
}
