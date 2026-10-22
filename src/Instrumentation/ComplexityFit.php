<?php

declare(strict_types=1);

namespace AlgorithmsLab\Instrumentation;

use InvalidArgumentException;

/**
 * Ordinary least squares on logarithms.
 *
 * A power law `value = a * size^b` becomes a straight line after taking logs,
 * so the slope is the exponent. This is the only fitting method in the lab:
 * a single power law is easy to explain, easy to challenge, and enough to show
 * that a measured curve is not the shape the theory predicted.
 */
final class ComplexityFit
{
    /**
     * @param list<int|float> $sizes
     * @param list<int|float> $values
     */
    public static function powerLaw(array $sizes, array $values): FitResult
    {
        if (count($sizes) !== count($values)) {
            throw new InvalidArgumentException('Sizes and values must have the same length');
        }

        if (count($sizes) < 2) {
            throw new InvalidArgumentException('A fit needs at least two points');
        }

        $xs = [];
        $ys = [];

        foreach ($sizes as $index => $size) {
            $x = (float) $size;
            $y = (float) $values[$index];

            if ($x <= 0.0 || $y <= 0.0) {
                throw new InvalidArgumentException('Log-log fitting needs positive sizes and values');
            }

            $xs[] = log($x);
            $ys[] = log($y);
        }

        $count = count($xs);
        $meanX = array_sum($xs) / $count;
        $meanY = array_sum($ys) / $count;
        $ssX = 0.0;
        $ssY = 0.0;
        $ssXY = 0.0;

        for ($i = 0; $i < $count; ++$i) {
            $dx = $xs[$i] - $meanX;
            $dy = $ys[$i] - $meanY;
            $ssX += $dx * $dx;
            $ssY += $dy * $dy;
            $ssXY += $dx * $dy;
        }

        if ($ssX <= 0.0) {
            throw new InvalidArgumentException('Sizes must not all be identical');
        }

        $slope = $ssXY / $ssX;
        $intercept = $meanY - $slope * $meanX;
        $ssResidual = 0.0;

        for ($i = 0; $i < $count; ++$i) {
            $predicted = $intercept + $slope * $xs[$i];
            $residual = $ys[$i] - $predicted;
            $ssResidual += $residual * $residual;
        }

        $rSquared = $ssY > 0.0 ? 1.0 - $ssResidual / $ssY : 1.0;

        return new FitResult(
            slope: $slope,
            intercept: $intercept,
            rSquared: $rSquared,
            samples: $count,
        );
    }
}
