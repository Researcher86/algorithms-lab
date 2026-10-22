<?php

declare(strict_types=1);

namespace AlgorithmsLab\Instrumentation;

/**
 * The result of a log-log fit: `log(value) = intercept + slope * log(size)`.
 *
 * The slope is the exponent a reader would put in O(n^b). R-squared says how
 * well the measured points support that exponent; it is not a proof and the
 * sample count is kept so a report cannot quietly imply more evidence than it
 * has.
 */
final readonly class FitResult
{
    public function __construct(
        public float $slope,
        public float $intercept,
        public float $rSquared,
        public int $samples,
    ) {
    }

    /**
     * @return array<string, float|int>
     */
    public function toArray(): array
    {
        return [
            'slope' => $this->slope,
            'intercept' => $this->intercept,
            'r_squared' => $this->rSquared,
            'samples' => $this->samples,
        ];
    }
}
