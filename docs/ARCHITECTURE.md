# Architecture — Algorithms Lab

This document maps the pieces that every chapter shares. The chapter-specific
implementations are added one at a time; the harness below is what makes their
measurements comparable.

## The flow

```text
experiments/NN-topic/*.php          benchmarks/*.php
        │                                  │
        │ returns an Experiment            │ returns a list of Benchmark
        ▼                                  ▼
  ExperimentRegistry                BenchmarkRunner
        │                                  │
        ▼                                  ▼
  ExperimentRunner                  Timings + Environment
        │                                  │
        ▼                                  ▼
  Output (text / JSON)              Formatter (text / JSON / CSV)
        │                                  │
        └──────────────┬───────────────────┘
                       ▼
              docs/BENCHMARKS.md
              book chapter (pinned output)
```

## Module map

| Module | Responsibility | Depends on |
|---|---|---|
| Foundation | Data structures and small primitives with no I/O: binary heap, indexed min-heap, bit array, hashers, comparator, seeded random, Zipf sampler, union-find | nothing |
| Instrumentation | Counters, stopwatch, memory meter and the Measure facade | Foundation |
| Experiment | Descriptor, discovery, option validation, output, runner | Instrumentation |
| Benchmark | Descriptor, repetitions, timings, environment, formatters | Instrumentation |
| Cli | `--name=value` parsing shared by both entry points | nothing |
| Chapter modules | Hash table, Bloom, cache, graph, tree, ring, stream, external sort | Foundation, Instrumentation |

The cache module is a good example of the split: `Cache` and `CacheStats` are
the contract, `LRUCache`/`LFUCache`/`HeapLFU` are the policies under study,
and `NaiveLRU`/`ArrayLRU` are baselines. The experiment knows about hit rates
and workloads; the cache knows only about keys, values and policy invariants.

## Experiment contract

An experiment is a PHP file under `experiments/*/*.php` that returns an
`Experiment` object. The file must not perform the experiment at include time:
the registry loads every descriptor to build the list, so allocating a large
dataset there would make `make experiment` slow and surprising.

```php
return new Experiment(
    name: 'demo:smoke',
    description: 'Checks the harness end to end.',
    supports: ['elements', 'seed'],
    run: static function (Options $options, Output $output): void {
        // Print prose, record named measurements.
    },
);
```

The name is stable and namespaced (`topic:question`). The `supports` list is
the complete set of options the closure reads; the runner refuses anything
else. Measurements are scalars and are what JSON output carries.

## Benchmark contract

A benchmark file under `benchmarks/` returns a list of `Benchmark` objects.
The iteration count belongs to the benchmark, because one operation may need
thousands of calls to rise above timer noise while another needs one. The
runner adds warm-up and repetitions on top.

A benchmark reports the environment with every result:

- PHP version, OS, kernel, architecture and CPU count;
- `memory_limit`, cgroup memory limit;
- OPcache and JIT state;
- Xdebug mode;
- allocator;
- git commit.

None of these fields is optional. A number without them describes a machine,
not an algorithm.

## Where the code lives

- `src/Foundation/` — primitives shared by chapters.
- `src/Instrumentation/` — measurement tools with no domain knowledge.
- `src/Experiment/` and `src/Benchmark/` — the harness.
- `src/Cli/` — argument parsing.
- Chapter modules (`src/HashTable/`, `src/Cache/`, and so on) — implementations
  and their baselines. Each has a matching test directory.
- Chapter 01 is split into `src/HashTable/` (`ChainingHashTable`,
  `OpenAddressingHashTable`, `HashFunction`) and `src/BloomFilter/`.
- Chapter 02 lives in `src/Cache/`: `Cache`/`CacheStats` for the contract,
  `LRUCache` and `LFUCache` for the policies, `HeapLFU` for the O(log n)
  comparison, and `NaiveLRU`/`ArrayLRU` for the baselines. The shared
  `ZipfDistribution` is in `src/Foundation/` because later chapters also need
  skewed request sequences.
- Cache experiments are `cache:zipf`, `cache:scan`, `cache:loop`,
  `cache:shift` and `cache:memory`; the benchmark suite is `cache`.
- Chapter 03 lives in `src/Graph/`. `Graph` and `MutableGraph` are the contract;
  `AdjacencyList`, `AdjacencyMatrix`, `EdgeList` and `CsrGraph` are the four
  representations; `Bfs`, `Dfs`, `Components`, `TopologicalSort`, `Dijkstra`,
  `DijkstraDecreaseKey`, `SplDijkstra`, `BellmanFord`, `Kruskal` and `Prim`
  are the algorithms. `GraphGenerator` is the only place that knows how to
  build path, star, grid, complete, random and random-DAG edge lists.
- Graph experiments are `graph:representations`, `graph:traversal`,
  `graph:dijkstra`, `graph:topological-sort` and `graph:dsu`; the benchmark
  suite is `graph`.
- Chapter 04 lives in `src/Tree/`. `BTreeNode` and `BTree` implement the
  textbook minimum-degree B-tree with pre-emptive insertion splits and
  borrow/merge deletion. `BPlusNode` and `BPlusTree` implement the linked-leaf
  B+ tree with point search, range scans and a sorted bulk loader.
  `SortedArrayIndex` and `PhpArrayIndex` are the flat baselines;
  `PageSimulator` is the page-cost model; `KeyGenerator` produces reproducible
  shuffled, ascending and descending key sets.
- Tree experiments are `tree:height`, `tree:insert-patterns`, `tree:range`,
  `tree:page-reads` and `tree:memory`; the benchmark suite is `tree`.
- Chapter 05 lives in `src/ConsistentHash/`. `NodeHash` is the minimal contract
  shared by `ModuloHash`, `HashRing` and `RendezvousHash`. `HashRing` owns
  sorted virtual-token positions, binary-search lookup and `getNodes()` for the
  clockwise replica order; `ModuloHash` is the remapping baseline and
  `RendezvousHash` is the highest-random-weight alternative. The ring resolves
  token collisions by probing the next free position.
- Ring experiments are `ring:remap`, `ring:balance` and `ring:replicas`; the
  benchmark suite is `ring`.
- Chapter 06 lives in `src/Stream/`. `TopKHeap` is an indexed min-heap over K
  entries plus an exact count map for every distinct key; `QuickSelect` is the
  exact batch alternative; `CountMinSketch` is a fixed-size estimator that
  cannot enumerate its keys; `SpaceSaving` and `MisraGries` are bounded
  candidate summaries. `StreamGenerator` produces reproducible uniform, Zipf,
  all-unique and drift streams. The bounded-memory test runs in a child PHP
  process with a lowered `memory_limit`.
- Stream experiments are `stream:topk`, `stream:heavy-hitters`,
  `stream:all-unique` and `stream:drift`; the benchmark suite is `stream`.
- Chapter 07 lives in `src/ExternalSort/`. `ExternalSorter` reads records into
  byte-bounded chunks, sorts each chunk with the configured comparator, spills
  sorted runs and merges at most `fanIn` runs at a time through a binary heap.
  `ChunkReader` works over any iterable, `LineReader` streams newline records,
  `MergeEntry` carries the run number used for stable ties, and `SortStats`
  records runs, passes, logical bytes and comparisons. `DatasetGenerator` and
  `DatasetCache` provide deterministic 76-byte random, sorted, reversed and
  duplicate-heavy inputs. Temporary runs are created with `tempnam()` and
  removed in a `finally` block.
- External-sort experiments are `sort:memory-limit`, `sort:fan-in`,
  `sort:patterns` and `sort:io`; the benchmark suite is `external-sort`. The
  `tests/ExternalSort/BoundedMemoryTest.php` child runs an 8 MiB input under
  `memory_limit=8M`; the exact in-memory alternative is checked to fail there.
- `src/Foundation/IndexedMinHeap.php` is the decrease-key heap used by
  `DijkstraDecreaseKey`; `UnionFind` accepts path-compression and union-by-rank
  flags plus an optional `Counters`, which is what makes the DSU experiment a
  controlled comparison instead of four copies of the same code.
- `experiments/` — one runnable question per file, grouped by chapter.
- `benchmarks/` — one suite per file, grouped by topic.

## Design rules

1. **Descriptors, not base classes.** Experiments and benchmarks are small
   readonly objects with closures. A chapter is readable top to bottom, and
   the harness does not impose a template method on it.
2. **Discovery, not a hand-written registry.** A renamed or moved experiment
   changes no central list.
3. **Refuse unknown options.** A silently ignored option is how a result gets
   published under a configuration it never ran with.
4. **Measure in isolation.** No benchmark includes dataset generation unless
   the question is about dataset generation; warm-up is discarded.
5. **Counters are domain-specific.** The harness provides the container; each
   chapter decides which operations to count.
