<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Instrumentation;

use AlgorithmsLab\Instrumentation\ComplexityFit;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ComplexityFitTest extends TestCase
{
    public function testRecoversAQuadraticExponent(): void
    {
        $fit = ComplexityFit::powerLaw([1, 2, 4, 8, 16], [3, 12, 48, 192, 768]);

        self::assertEqualsWithDelta(2.0, $fit->slope, 1e-10);
        self::assertEqualsWithDelta(log(3.0), $fit->intercept, 1e-10);
        self::assertEqualsWithDelta(1.0, $fit->rSquared, 1e-10);
        self::assertSame(5, $fit->samples);
    }

    public function testRecoversALinearExponent(): void
    {
        $fit = ComplexityFit::powerLaw([10, 100, 1000], [2, 20, 200]);

        self::assertEqualsWithDelta(1.0, $fit->slope, 1e-10);
        self::assertEqualsWithDelta(log(0.2), $fit->intercept, 1e-10);
        self::assertEqualsWithDelta(1.0, $fit->rSquared, 1e-10);
    }

    public function testReturnsAnArrayForReports(): void
    {
        $fit = ComplexityFit::powerLaw([1, 2], [1, 2]);
        $array = $fit->toArray();

        self::assertSame(1.0, $array['slope']);
        self::assertSame(2, $array['samples']);
    }

    public function testRejectsMismatchedLengths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ComplexityFit::powerLaw([1, 2], [1]);
    }

    public function testRejectsFewerThanTwoPoints(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ComplexityFit::powerLaw([1], [1]);
    }

    public function testRejectsNonPositiveValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ComplexityFit::powerLaw([1, 2], [0, 2]);
    }

    public function testRejectsIdenticalSizes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ComplexityFit::powerLaw([2, 2], [1, 2]);
    }
}
