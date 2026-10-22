<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Graph;

use AlgorithmsLab\Graph\AdjacencyList;
use AlgorithmsLab\Graph\AdjacencyMatrix;
use AlgorithmsLab\Graph\CsrGraph;
use AlgorithmsLab\Graph\Edge;
use AlgorithmsLab\Graph\EdgeList;
use AlgorithmsLab\Graph\MutableGraph;
use Closure;
use InvalidArgumentException;
use OutOfRangeException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GraphRepresentationTest extends TestCase
{
    /**
     * @return array<string, array{Closure(int, bool): MutableGraph}>
     */
    public static function mutableGraphs(): array
    {
        return [
            'adjacency-list' => [static fn (int $vertices, bool $directed): MutableGraph => new AdjacencyList($vertices, $directed)],
            'adjacency-matrix' => [static fn (int $vertices, bool $directed): MutableGraph => new AdjacencyMatrix($vertices, $directed)],
            'edge-list' => [static fn (int $vertices, bool $directed): MutableGraph => new EdgeList($vertices, $directed)],
        ];
    }

    #[DataProvider('mutableGraphs')]
    public function testUndirectedGraphStoresBothDirectionsAndOneEdge(Closure $factory): void
    {
        $graph = $factory(4, false);
        $graph->addEdge(0, 1, 2.5);
        $graph->addEdge(1, 2, 1.0);
        $graph->addEdge(2, 0, 4.0);
        $graph->addEdge(3, 3, 0.5);

        self::assertSame(4, $graph->vertexCount());
        self::assertSame(4, $graph->edgeCount());
        self::assertFalse($graph->directed());
        self::assertSame([1 => 2.5, 2 => 4.0], $graph->neighbors(0));
        self::assertSame([0 => 2.5, 2 => 1.0], $graph->neighbors(1));
        self::assertSame([3 => 0.5], $graph->neighbors(3));
        self::assertSame(2.5, $graph->weight(1, 0));
        self::assertTrue($graph->hasEdge(0, 2));
        self::assertFalse($graph->hasEdge(0, 3));

        $edges = $this->edgeMap($graph->edges());
        self::assertSame(['0:1' => 2.5, '0:2' => 4.0, '1:2' => 1.0, '3:3' => 0.5], $edges);

        $graph->addEdge(0, 1, 9.0);

        self::assertSame(4, $graph->edgeCount());
        self::assertSame(9.0, $graph->weight(0, 1));
    }

    #[DataProvider('mutableGraphs')]
    public function testDirectedGraphKeepsDirection(Closure $factory): void
    {
        $graph = $factory(3, true);
        $graph->addEdge(0, 1, 1.5);
        $graph->addEdge(1, 2, 2.5);
        $graph->addEdge(0, 2, 4.5);

        self::assertTrue($graph->directed());
        self::assertSame(3, $graph->edgeCount());
        self::assertSame([1 => 1.5, 2 => 4.5], $graph->neighbors(0));
        self::assertSame([2 => 2.5], $graph->neighbors(1));
        self::assertFalse($graph->hasEdge(1, 0));
        self::assertSame(['0:1' => 1.5, '0:2' => 4.5, '1:2' => 2.5], $this->edgeMap($graph->edges()));
    }

    #[DataProvider('mutableGraphs')]
    public function testRejectsUnknownVerticesAndNonFiniteWeights(Closure $factory): void
    {
        $graph = $factory(3, false);

        try {
            $graph->addEdge(3, 0);
            self::fail('Expected an unknown-vertex exception');
        } catch (OutOfRangeException) {
            self::assertSame(0, $graph->edgeCount());
        }

        $this->expectException(InvalidArgumentException::class);
        $graph->addEdge(0, 1, INF);
    }

    public function testCsrMatchesTheUndirectedContract(): void
    {
        $graph = CsrGraph::fromEdges(4, [
            new Edge(0, 1, 2.5),
            new Edge(1, 2, 1.0),
            new Edge(2, 0, 4.0),
            new Edge(3, 3, 0.5),
        ]);

        self::assertSame(4, $graph->vertexCount());
        self::assertSame(4, $graph->edgeCount());
        self::assertFalse($graph->directed());
        self::assertSame([1 => 2.5, 2 => 4.0], $graph->neighbors(0));
        self::assertSame([0 => 2.5, 2 => 1.0], $graph->neighbors(1));
        self::assertSame(['0:1' => 2.5, '0:2' => 4.0, '1:2' => 1.0, '3:3' => 0.5], $this->edgeMap($graph->edges()));
        self::assertSame(2.5, $graph->weight(1, 0));
        self::assertTrue($graph->hasEdge(0, 2));
        self::assertFalse($graph->hasEdge(0, 3));
    }

    public function testCsrCanonicalisesUndirectedEdges(): void
    {
        $graph = CsrGraph::fromEdges(2, [new Edge(1, 0, 7.0)]);

        self::assertSame(['0:1' => 7.0], $this->edgeMap($graph->edges()));
        self::assertSame([1 => 7.0], $graph->neighbors(0));
        self::assertSame([0 => 7.0], $graph->neighbors(1));
    }

    public function testCsrSupportsDirectedGraphs(): void
    {
        $graph = CsrGraph::fromEdges(3, [
            new Edge(0, 1, 1.5),
            new Edge(1, 2, 2.5),
            new Edge(0, 2, 4.5),
        ], true);

        self::assertTrue($graph->directed());
        self::assertSame(3, $graph->edgeCount());
        self::assertSame([1 => 1.5, 2 => 4.5], $graph->neighbors(0));
        self::assertSame([2 => 2.5], $graph->neighbors(1));
        self::assertFalse($graph->hasEdge(1, 0));
    }

    public function testCsrDeduplicatesARepeatedEdge(): void
    {
        $graph = CsrGraph::fromEdges(2, [
            new Edge(0, 1, 1.0),
            new Edge(1, 0, 2.0),
        ]);

        self::assertSame(1, $graph->edgeCount());
        self::assertSame(1.0, $graph->weight(0, 1));
    }

    #[DataProvider('mutableGraphs')]
    public function testZeroWeightIsAPresentEdge(Closure $factory): void
    {
        $graph = $factory(2, false);
        $graph->addEdge(0, 1, 0.0);

        self::assertTrue($graph->hasEdge(0, 1));
        self::assertSame(0.0, $graph->weight(1, 0));
        self::assertSame([1 => 0.0], $graph->neighbors(0));
    }

    /**
     * @param list<Edge> $edges
     * @return array<string, float>
     */
    private function edgeMap(array $edges): array
    {
        $map = [];

        foreach ($edges as $edge) {
            $map[$edge->from . ':' . $edge->to] = $edge->weight;
        }

        ksort($map);

        return $map;
    }
}
