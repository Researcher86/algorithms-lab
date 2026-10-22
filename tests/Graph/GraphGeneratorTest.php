<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Graph;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Graph\GraphGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GraphGeneratorTest extends TestCase
{
    public function testPathAndStarHaveTheExpectedEdgeCounts(): void
    {
        self::assertCount(4, GraphGenerator::path(5));
        self::assertCount(4, GraphGenerator::star(5));
        self::assertCount(0, GraphGenerator::path(1));
        self::assertCount(0, GraphGenerator::star(0));
    }

    public function testGridConnectsRightAndDownNeighbours(): void
    {
        $edges = GraphGenerator::grid(2, 3);

        self::assertCount(7, $edges);
        self::assertSame(['0:1', '0:3', '1:2', '1:4', '2:5', '3:4', '4:5'], $this->keys($edges));
    }

    public function testCompleteGraphCounts(): void
    {
        self::assertCount(6, GraphGenerator::complete(4));
        self::assertCount(12, GraphGenerator::complete(4, 1.0, true));
    }

    public function testRandomGraphsAreSimpleAndReproducible(): void
    {
        $left = GraphGenerator::random(30, 60, new DeterministicRandom('random-left'), false, 10.0);
        $right = GraphGenerator::random(30, 60, new DeterministicRandom('random-left'), false, 10.0);

        self::assertCount(60, $left);
        self::assertSame($this->keys($left), $this->keys($right));

        foreach ($left as $edge) {
            self::assertNotSame($edge->from, $edge->to);
            self::assertGreaterThanOrEqual(1.0, $edge->weight);
            self::assertLessThanOrEqual(10.0, $edge->weight);
        }
    }

    public function testRandomDagOnlyPointsForward(): void
    {
        $edges = GraphGenerator::randomDag(20, 40, new DeterministicRandom('dag'));

        self::assertCount(40, $edges);

        foreach ($edges as $edge) {
            self::assertLessThan($edge->to, $edge->from);
        }
    }

    public function testRandomRejectsMoreEdgesThanTheSimpleGraphAllows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GraphGenerator::random(3, 4, new DeterministicRandom('too-many'));
    }

    public function testNegativeVerticesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GraphGenerator::path(-1);
    }

    /**
     * @param list<\AlgorithmsLab\Graph\Edge> $edges
     * @return list<string>
     */
    private function keys(array $edges): array
    {
        $keys = [];

        foreach ($edges as $edge) {
            $keys[] = $edge->from . ':' . $edge->to;
        }

        return $keys;
    }
}
