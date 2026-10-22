<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests\Graph;

use AlgorithmsLab\Foundation\DeterministicRandom;
use AlgorithmsLab\Graph\AdjacencyList;
use AlgorithmsLab\Graph\BellmanFord;
use AlgorithmsLab\Graph\Bfs;
use AlgorithmsLab\Graph\Components;
use AlgorithmsLab\Graph\Dfs;
use AlgorithmsLab\Graph\Dijkstra;
use AlgorithmsLab\Graph\DijkstraDecreaseKey;
use AlgorithmsLab\Graph\GraphGenerator;
use AlgorithmsLab\Graph\Kruskal;
use AlgorithmsLab\Graph\Prim;
use AlgorithmsLab\Graph\SplDijkstra;
use AlgorithmsLab\Graph\TopologicalSort;
use InvalidArgumentException;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;

final class GraphAlgorithmsTest extends TestCase
{
    public function testBfsComputesDistancesAndParents(): void
    {
        $graph = $this->path(4);
        $result = Bfs::run($graph, 0);

        self::assertSame([0, 1, 2, 3], $result->order);
        self::assertSame([0, 1, 2, 3], $result->distances);
        self::assertSame([null, 0, 1, 2], $result->parents);
        self::assertSame(3, $result->maxDistance());
        self::assertTrue($result->visited(3));
    }

    public function testBfsLeavesUnreachableVerticesAtMinusOne(): void
    {
        $graph = new AdjacencyList(4);
        $graph->addEdge(0, 1);
        $result = Bfs::run($graph, 0);

        self::assertSame([0, 1, -1, -1], $result->distances);
        self::assertSame([null, 0, null, null], $result->parents);
    }

    public function testDfsProducesPreorderAndPostorder(): void
    {
        $result = Dfs::run($this->path(4), 0);

        self::assertSame([0, 1, 2, 3], $result->order);
        self::assertSame([3, 2, 1, 0], $result->postorder);
        self::assertSame([null, 0, 1, 2], $result->parents);
    }

    public function testDfsSkipsAlreadySeenSiblings(): void
    {
        $graph = new AdjacencyList(4);
        $graph->addEdge(0, 1);
        $graph->addEdge(0, 2);
        $graph->addEdge(1, 3);
        $result = Dfs::run($graph, 0);

        self::assertCount(4, $result->order);
        self::assertCount(4, $result->postorder);
        self::assertSame([0, 1, 3, 2], $result->order);
    }

    public function testComponentsReturnsEachConnectedComponent(): void
    {
        $graph = new AdjacencyList(5);
        $graph->addEdge(0, 1);
        $graph->addEdge(2, 3);

        self::assertSame([[0, 1], [2, 3], [4]], Components::undirected($graph));
    }

    public function testComponentsRejectsDirectedGraphs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Components::undirected(new AdjacencyList(2, true));
    }

    public function testBothTopologicalSortsReturnValidOrders(): void
    {
        $graph = new AdjacencyList(5, true);
        $graph->addEdge(0, 1);
        $graph->addEdge(0, 2);
        $graph->addEdge(1, 3);
        $graph->addEdge(2, 3);
        $graph->addEdge(3, 4);

        foreach ([TopologicalSort::kahn($graph), TopologicalSort::dfs($graph)] as $order) {
            self::assertNotNull($order);
            self::assertTrue($this->isTopologicalOrder($graph, $order));
        }
    }

    public function testTopologicalSortReturnsNullOnACycle(): void
    {
        $graph = new AdjacencyList(3, true);
        $graph->addEdge(0, 1);
        $graph->addEdge(1, 2);
        $graph->addEdge(2, 0);

        self::assertNull(TopologicalSort::kahn($graph));
        self::assertNull(TopologicalSort::dfs($graph));
    }

    public function testTopologicalSortRejectsUndirectedGraphs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TopologicalSort::kahn(new AdjacencyList(2));
    }

    public function testDijkstraFindsTheShortestPathTree(): void
    {
        $graph = $this->weightedGraph();
        $result = Dijkstra::run($graph, 0);

        self::assertSame([0.0, 3.0, 1.0, 4.0, 7.0, INF], $result->distances);
        self::assertSame(4.0, $result->distance(3));
        self::assertSame(7.0, $result->distance(4));
        self::assertNull($result->distance(5));
        self::assertFalse($result->reachable(5));
        self::assertGreaterThan(0, $result->relaxations);
    }

    public function testDijkstraRejectsNegativeWeights(): void
    {
        $graph = new AdjacencyList(3, true);
        $graph->addEdge(0, 1, 1.0);
        $graph->addEdge(1, 2, -2.0);

        $this->expectException(InvalidArgumentException::class);

        Dijkstra::run($graph, 0);
    }

    public function testBellmanFordHandlesNegativeEdges(): void
    {
        $graph = new AdjacencyList(3, true);
        $graph->addEdge(0, 1, 4.0);
        $graph->addEdge(0, 2, 5.0);
        $graph->addEdge(1, 2, -2.0);
        $result = BellmanFord::run($graph, 0);

        self::assertFalse($result->negativeCycle);
        self::assertSame(2.0, $result->distance(2));
    }

    public function testBellmanFordDetectsANegativeCycle(): void
    {
        $graph = new AdjacencyList(3, true);
        $graph->addEdge(0, 1, 1.0);
        $graph->addEdge(1, 2, -2.0);
        $graph->addEdge(2, 1, -2.0);
        $result = BellmanFord::run($graph, 0);

        self::assertTrue($result->negativeCycle);
    }

    public function testDijkstraAndBellmanFordAgreeOnPositiveRandomGraphs(): void
    {
        $edges = GraphGenerator::random(40, 120, new DeterministicRandom('dijkstra-bellman'), false, 20.0);
        $graph = new AdjacencyList(40);
        $this->addEdges($graph, $edges);

        for ($source = 0; $source < 5; ++$source) {
            $dijkstra = Dijkstra::run($graph, $source);
            $decreaseKey = DijkstraDecreaseKey::run($graph, $source);
            $spl = SplDijkstra::run($graph, $source);
            $bellman = BellmanFord::run($graph, $source);

            self::assertSame($bellman->distances, $dijkstra->distances);
            self::assertSame($dijkstra->distances, $decreaseKey->distances);
            self::assertSame($dijkstra->distances, $spl->distances);
        }
    }

    public function testKruskalAndPrimFindTheSameMstWeight(): void
    {
        $graph = $this->mstGraph();

        $kruskal = Kruskal::run($graph);
        $prim = Prim::run($graph, 0);

        self::assertSame(7.0, $kruskal->weight);
        self::assertSame(7.0, $prim->weight);
        self::assertCount(3, $kruskal->edges);
        self::assertCount(3, $prim->edges);
    }

    public function testKruskalReturnsAForestForDisconnectedGraphs(): void
    {
        $graph = new AdjacencyList(4);
        $graph->addEdge(0, 1, 1.0);
        $graph->addEdge(2, 3, 2.0);
        $result = Kruskal::run($graph);

        self::assertSame(3.0, $result->weight);
        self::assertCount(2, $result->edges);
    }

    public function testPrimOnlyCoversTheStartComponent(): void
    {
        $graph = new AdjacencyList(4);
        $graph->addEdge(0, 1, 1.0);
        $graph->addEdge(2, 3, 2.0);
        $result = Prim::run($graph, 0);

        self::assertSame(1.0, $result->weight);
        self::assertCount(1, $result->edges);
    }

    public function testMstAlgorithmsRejectDirectedGraphs(): void
    {
        $graph = new AdjacencyList(2, true);

        try {
            Kruskal::run($graph);
            self::fail('Expected Kruskal to reject a directed graph');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $graph->edgeCount());
        }

        $this->expectException(InvalidArgumentException::class);
        Prim::run($graph);
    }

    public function testInvalidSourceIsRejected(): void
    {
        $this->expectException(OutOfRangeException::class);

        Bfs::run($this->path(3), 3);
    }

    private function path(int $vertices): AdjacencyList
    {
        $graph = new AdjacencyList($vertices);

        for ($vertex = 0; $vertex + 1 < $vertices; ++$vertex) {
            $graph->addEdge($vertex, $vertex + 1);
        }

        return $graph;
    }

    private function weightedGraph(): AdjacencyList
    {
        $graph = new AdjacencyList(6);
        $graph->addEdge(0, 1, 4.0);
        $graph->addEdge(0, 2, 1.0);
        $graph->addEdge(2, 1, 2.0);
        $graph->addEdge(1, 3, 1.0);
        $graph->addEdge(2, 3, 5.0);
        $graph->addEdge(3, 4, 3.0);

        return $graph;
    }

    private function mstGraph(): AdjacencyList
    {
        $graph = new AdjacencyList(4);
        $graph->addEdge(0, 1, 1.0);
        $graph->addEdge(1, 2, 2.0);
        $graph->addEdge(0, 2, 3.0);
        $graph->addEdge(2, 3, 4.0);
        $graph->addEdge(1, 3, 5.0);

        return $graph;
    }

    /**
     * @param list<\AlgorithmsLab\Graph\Edge> $edges
     */
    private function addEdges(AdjacencyList $graph, array $edges): void
    {
        foreach ($edges as $edge) {
            $graph->addEdge($edge->from, $edge->to, $edge->weight);
        }
    }

    /**
     * @param list<int> $order
     */
    private function isTopologicalOrder(AdjacencyList $graph, array $order): bool
    {
        $position = array_flip($order);

        foreach ($graph->edges() as $edge) {
            if ($position[$edge->from] >= $position[$edge->to]) {
                return false;
            }
        }

        return true;
    }
}
