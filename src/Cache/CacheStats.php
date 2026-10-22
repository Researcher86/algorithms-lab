<?php

declare(strict_types=1);

namespace AlgorithmsLab\Cache;

/**
 * Hit, miss and eviction counts for one cache run.
 *
 * A cache can be fast and still be useless if it misses the workload. Keeping
 * the counts in the cache, rather than in the experiment, means every
 * implementation reports the same definition of a hit.
 */
final class CacheStats
{
    public int $hits = 0;

    public int $misses = 0;

    public int $evictions = 0;

    public function recordHit(): void
    {
        ++$this->hits;
    }

    public function recordMiss(): void
    {
        ++$this->misses;
    }

    public function recordEviction(): void
    {
        ++$this->evictions;
    }

    public function requests(): int
    {
        return $this->hits + $this->misses;
    }

    public function hitRate(): float
    {
        $requests = $this->requests();

        return $requests === 0 ? 0.0 : $this->hits / $requests;
    }

    public function reset(): void
    {
        $this->hits = 0;
        $this->misses = 0;
        $this->evictions = 0;
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'evictions' => $this->evictions,
            'requests' => $this->requests(),
        ];
    }
}
