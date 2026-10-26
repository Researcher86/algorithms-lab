# Algorithms Lab — How It Was Built

This is the build journal for the project. Each phase states its goal, the
tasks that made it real, a definition of done, and the tests that hold it. The
work is done in the container; a phase is marked complete only after
`make test`, `make analyse` and `make format-check` pass there, and after CI
reproduces the same result.

Status legend: ✅ complete, 🚧 in progress, ⏳ planned.

---

## 1. Where this project fits

```text
engineering-lab/                     # workspace, not a git repository
├── book/                            # git: Researcher86/engineering-lab-book
│   ├── README.md                    # series table of contents
│   └── algorithms-lab/              # seven book chapters (RU)
├── algorithms-lab/                  # git: Researcher86/algorithms-lab — this project
│   ├── src/                         # implementations + harness
│   ├── experiments/                 # runnable experiments
│   ├── benchmarks/                  # repeated measurements
│   └── docs/                        # engineering documentation (EN)
├── php-systems-lab/                 # nine sibling systems projects
└── architecture-lab/ ...            # other labs in the series
```

### Roles

| Component | Role |
|---|---|
| `book/algorithms-lab` | Russian narrative: seven chapters, each explaining a mechanism and linking to code |
| `algorithms-lab` | Executable source of truth: code, experiments, measurements, engineering docs in English |
| `book/php-systems-lab/tools/check_book.py` | Verifies that code blocks in the book match pinned source |
| `php-systems-lab/*` | Sibling labs: shared conventions and the systems where the algorithms are used |
| `engineering-lab-book` | Umbrella book repository and CI |

### Contract with the ecosystem

1. **One chapter = one module.** The order and numbers are fixed by
   `book/algorithms-lab/README.md`: 01 Hash/Bloom, 02 Cache, 03 Graphs,
   04 B-Tree, 05 Consistent Hashing, 06 Top-K, 07 External Sorting.
2. **The book is written from the code.** Implementation, tests and a
   measurement come first; the chapter quotes real files and real output.
3. **Links in the book are pinned.** Every link targets a full SHA or the
   `v1.0.0` tag, never a branch. The repository is public.
4. **Languages.** Code, comments, README and `docs/` are English. Book
   chapters are Russian.
5. **One command per experiment.** `make experiment ARGS="demo:smoke"`
   is the interface the book can quote.
6. **The book is checked mechanically.** The book checker must learn seven
   chapters for this project; it currently assumes the 26 chapters of
   php-systems-lab.
7. **One repository for the whole lab.** Seven repositories would multiply the
   shared foundation and harness and force the book to name seven remotes.

---

## 2. Principles

1. **Baseline first.** Every structure has a naive implementation or a PHP
   built-in to compare against.
2. **Counters before stopwatches.** Comparisons, probes, collisions and node
   reads prove complexity; wall-clock time is the secondary, noisier metric.
3. **Reproducibility is part of the result.** Seed, PHP version, OPcache/JIT,
   Xdebug mode, memory limit and commit travel with every number.
4. **Real and adversarial data.** Zipf, sorted, reversed, duplicated and
   collision-heavy inputs are first-class, not an afterthought.
5. **Isolation.** Time and memory are measured in a controlled process; Xdebug
   is off; warm-up and repetitions are mandatory.
6. **Documentation is part of the definition of done.** No report and no
   conclusion means the topic is not finished.
7. **The code is the source for the book.** Chapters link to concrete files and
   line ranges, not to a paraphrase.
8. **Minimal dependencies.** No runtime dependencies; Composer is for
   development tools only.

### Non-goals

- A web interface, database, Kubernetes, microservices or production support.
- A production-ready library with SemVer guarantees.
- LeetCode-style problems unrelated to the lab's topics.
- A separate repository per chapter.

---

## 3. Stack and ecosystem standards

| Aspect | Standard | Note |
|---|---|---|
| PHP | `^8.5`, CLI, 64-bit | `php:8.5-cli` in Docker |
| Composer | `researcher86/algorithms-lab`, PSR-4 `AlgorithmsLab\` → `src/`, `AlgorithmsLab\Tests\` → `tests/` | Same shape as the siblings |
| Runtime | No dependencies; `ext-pcntl`, `ext-posix` | Child processes, timeouts and memory-limit tests |
| Tests | PHPUnit `^11.5` | `phpunit.xml`, cache directory |
| Static analysis | PHPStan `^2`, level 8 | Paths: src, bin, tests, experiments, benchmarks |
| Style | PHP-CS-Fixer `^3` | `@PHP84Migration`, `@PSR12`, strict types, `strict_param` |
| Docker | `php:8.5-cli` + `pcntl`, `posix`, Composer, diagnostic tools | Xdebug installed, off by default |
| Orchestration | `docker-compose.yml`, service `php`, container `algorithms-lab` | Project mounted at `/app` |
| Make | `up/down/build/shell/htop/install/test/analyse/format/format-check/check/experiment/benchmark` + debug variants | CI calls the same targets |
| CI | GitHub Actions: build, install, test, analyse, format-check, down | No performance thresholds |
| License | MIT in `composer.json`; no separate LICENSE file | Same as php-memory-lab |
| Results | `var/results/`, `var/benchmarks/`, `var/datasets/` gitignored | Small fixtures live in `datasets/` |
| Release | `v1.0.0` after the chapters and integration | Book links target it |
| Language | Code/docs/README in English; book in Russian | Comments explain decisions |

### 3.1 Docker is the only supported environment

No host PHP, Composer, PHPUnit or extensions are required:

```text
host
  └── make <target>
        └── docker compose exec php <command>
              └── php:8.5-cli + pcntl + posix + Composer
```

Rules:

1. Every project command goes through `make`: `build`, `install`, `test`,
   `analyse`, `format`, `format-check`, `shell`, `experiment`, `benchmark`.
2. `make` never calls host PHP. If Docker is not running, the command fails
   with a Docker error, not with a different local PHP version.
3. CI calls the same targets in the same image. There is one environment.
4. PHP is pinned by the image, Composer by the `composer:2` image.
5. Xdebug is installed but `xdebug.mode=off` by default: the lab measures time.
   Debugging is opt-in through the `*-debug` targets with
   `XDEBUG_MODE=debug XDEBUG_TRIGGER=1`.
6. The repository is mounted at `/app`, so `var/` survives container restarts
   but stays out of git.
7. CI runs `make down` in an `always()` step.

Xdebug 3.5.3 is built from the pinned GitHub tag, not installed with PECL:
PECL returned "No releases available" and intermittently a 504. The GitHub tag
supports PHP 8.5, and the Docker `ARG` keeps the build reproducible.

Container configuration:

```text
memory_limit=512M
opcache.enable_cli=0
date.timezone=UTC
xdebug.mode=off
```

Tests that prove bounded memory lower the limit explicitly in a child process;
the project default does not decide what those tests may use.

**Docker on macOS is a virtualized Linux machine.** Absolute timings are
noisier and are not comparable with a native Linux host. Relative comparisons,
operation counters and memory measurements remain useful. Numbers quoted in
the book are taken in one fixed Linux environment and always include the
environment block.

---

## 4. Repository shape

```text
algorithms-lab/
├── bin/
│   ├── experiment              # run one experiment by name
│   └── benchmark               # run a benchmark suite
├── src/
│   ├── Foundation/             # heap, bits, hashers, comparator, random, union-find
│   ├── Instrumentation/        # counters, stopwatch, memory meter
│   ├── Experiment/             # descriptor, registry, runner, options, output
│   ├── Benchmark/              # runner, timings, environment, formatters
│   ├── Cli/                    # argument parsing and usage
│   ├── HashTable/              # chaining, open addressing
│   ├── BloomFilter/            # Bloom and counting Bloom
│   ├── Cache/                  # LRU, LFU, baselines
│   ├── Graph/                  # representations, traversals, Dijkstra, DSU
│   ├── Tree/                   # B-tree, B+tree, page simulation
│   ├── ConsistentHash/         # ring, rendezvous, modulo baseline
│   ├── Stream/                 # top-K, count-min, space-saving
│   └── ExternalSort/           # chunked sort, k-way merge
├── experiments/
│   ├── 00-demo/
│   ├── 01-hash-table/
│   ├── 02-bloom-filter/
│   ├── 03-cache/
│   ├── 04-graph/
│   ├── 05-tree/
│   ├── 06-consistent-hash/
│   ├── 07-stream/
│   └── 08-external-sort/
├── benchmarks/
├── datasets/                   # generators and small fixtures
├── tests/                      # mirrors src/, plus documentation checks
├── docs/
│   ├── PHASES.md
│   ├── DECISIONS.md
│   ├── BENCHMARKS.md
│   ├── ARCHITECTURE.md
│   └── COMPLEXITY.md
├── var/
│   ├── results/
│   ├── benchmarks/
│   └── datasets/
├── .github/workflows/ci.yml
├── Dockerfile
├── docker-compose.yml
├── Makefile
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── .php-cs-fixer.dist.php
├── .gitignore
└── README.md
```

Experiment files are discovered under `experiments/*/*.php`. Each returns an
`Experiment` descriptor; there is no hand-maintained registry. Names are stable
across moves: `hash:load-factor`, `bloom:fpr`, `cache:zipf`, `graph:dijkstra`,
`tree:height`, `ring:remap`, `stream:topk`, `sort:memory-limit`.

---

## 5. Chapter ↔ module ↔ experiment ↔ benchmark map

| # | Book chapter | Module | Core code | Experiments | Benchmark suite |
|---|---|---|---|---|---|
| 01 | Hash Table and Bloom Filter | HashTable, BloomFilter | ChainingHashTable, OpenAddressingHashTable, BloomFilter | hash:load-factor, hash:collisions, hash:memory, bloom:fpr | hash-table, bloom |
| 02 | LRU / LFU Cache | Cache | LRUCache, LFUCache, HeapLFU, NaiveLRU, ArrayLRU | cache:zipf, cache:scan, cache:loop, cache:shift, cache:memory | cache |
| 03 | Graph Algorithms | Graph | AdjacencyList, AdjacencyMatrix, EdgeList, CsrGraph, Bfs, Dfs, TopologicalSort, Dijkstra, DijkstraDecreaseKey, BellmanFord, Kruskal, Prim, UnionFind | graph:representations, graph:traversal, graph:dijkstra, graph:topological-sort, graph:dsu | graph |
| 04 | B-Tree and Search | Tree | BTree, BPlusTree, PageSimulator, SortedArrayIndex, PhpArrayIndex, KeyGenerator | tree:height, tree:insert-patterns, tree:range, tree:page-reads, tree:memory | tree |
| 05 | Consistent Hashing | ConsistentHash | NodeHash, HashRing, RendezvousHash, ModuloHash | ring:remap, ring:balance, ring:replicas | ring |
| 06 | Top-K and Stream Processing | Stream | TopKHeap, QuickSelect, CountMinSketch, SpaceSaving, MisraGries, StreamGenerator | stream:topk, stream:heavy-hitters, stream:all-unique, stream:drift | stream |
| 07 | External Sorting | ExternalSort | ExternalSorter, ChunkReader, MergeEntry, DatasetGenerator, SortStats | sort:memory-limit, sort:fan-in, sort:patterns, sort:io | external-sort |

Every row ends in a book chapter, tests, a table in `docs/BENCHMARKS.md`, and a
decision entry when there was a non-trivial trade-off.

---

## 6. Phase 0 — Docker environment ✅

### Goal

A reproducible environment in which every later experiment runs identically,
with no PHP, Composer or extensions installed on the host.

### Tasks

- [x] `Dockerfile` on `php:8.5-cli` with `pcntl`, `posix`, Composer, Xdebug
      3.5.3 (built from the pinned GitHub tag, off by default), `strace`,
      `time`, `procps`, `htop`.
- [x] PHP config: `memory_limit=512M`, `opcache.enable_cli=0`, UTC; tests may
      lower the limit in a child process.
- [x] `docker-compose.yml` with one `php` service and the project mounted at
      `/app`.
- [x] `Makefile` wrappers: `up`, `down`, `build`, `shell`, `htop`, `install`,
      `test`, `analyse`, `format`, `format-check`, `check`.
- [x] `composer.json` for `researcher86/algorithms-lab`, PSR-4
      `AlgorithmsLab\`, PHPUnit, PHPStan level 8, PHP-CS-Fixer.
- [x] `phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.dist.php`.
- [x] `.gitignore` for vendor, tool caches, `var/`, IDE and drafts.
- [x] GitHub Actions workflow that runs the same make targets in Docker.
- [x] `DocumentationTest` that checks docs links and backtick paths.
- [x] First real class and test: `src/Foundation/DeterministicRandom.php`.
- [x] Initial commit and public remote
      [`Researcher86/algorithms-lab`](https://github.com/Researcher86/algorithms-lab);
      CI is green on `master`.
- [x] Fold the original plan into this document and delete the root plan.

### Definition of Done

```bash
make build
make install
make test
make analyse
make format-check
```

All green in the container, CI reproduces the same on GitHub, and
`DocumentationTest` passes.

### Tests

- `tests/Foundation/DeterministicRandomTest.php` — same seed, same sequence;
  bounds; invalid ranges.
- `tests/DocumentationTest.php` — README links every document; relative links
  resolve; paths mentioned in backticks exist.

---

## 7. Phase 1 — Foundation and the measurement harness ✅

### Goal

One way to run an experiment and one way to measure, before the first topic.

### Foundation

- BinaryHeap — min/max with a comparator.
- BitArray — bit storage with get/set/popcount.
- Hashers — FNV-1a, Murmur3, xxh128, crc32.
- DeterministicRandom — seeded PRNG; already present.
- Comparator — comparison wrapper with a counter.
- UnionFind — path compression and union by rank.

### Instrumentation

- Counters: comparisons, probes, hash calls, collisions, node reads, swaps,
  rehashes.
- Stopwatch on `hrtime(true)`.
- MemoryMeter: usage, real usage, peak, baseline, `gc_collect_cycles`.
- Measure — the facade an experiment uses.

### Experiment harness

- Experiment — readonly descriptor: name, description, supports, closure.
- Registry — discovers `experiments/*/*.php`, requires an Experiment, refuses
  duplicate names, sorts.
- ExperimentRunner — validates options, runs the closure, records the result.
- Options — only declared options; an unknown option is an error, never
  silently ignored.
- Output — prose to stdout, measurements collected for JSON; `--output` only
  with `--format=json`.
- `bin/experiment` — list, run by name, text or JSON.

### Benchmark harness

- Benchmark — readonly descriptor: name, iterations, closure.
- BenchmarkRunner — warm-up + repetitions, min/max/mean/median/p95/MAD,
  ops/sec from the median, PHP memory delta.
- Environment — PHP, OS, CPU, cgroup limits, memory_limit, OPcache, JIT,
  Xdebug mode, allocator, git commit.
- Formatters — text, JSON, CSV.
- ComplexityFit — OLS on logarithms; used by experiments, not by time
  measurements.
- `bin/benchmark <suite> --format=... --output=... --iterations=...
  --repetitions=... --warmups=...`.

### Demo scenarios

- experiments/00-demo/smoke.php — the `demo:smoke` experiment shows the
  output shape.
- benchmarks/demo.php — the `demo` suite checks the harness in CI.
- `docs/BENCHMARKS.md` — how to read median/min/max and why the environment
  block is mandatory.

### Definition of Done

- `make experiment` prints the list; `make experiment ARGS="demo:smoke"` works.
- `make benchmark ARGS="demo"` prints a table with the environment block.
- `--format=json` is valid; `--output` writes a file.
- `DocumentationTest` checks commands from the docs.
- `make test analyse format-check` is green.

### Tests

- Foundation: heap ordering, bit boundaries, hash vectors, union-find
  invariants, comparator counts.
- Instrumentation: counter reset and increments, stopwatch monotonicity,
  memory snapshot shape, complexity-fit exponents and invalid input.
- CLI: command/option parsing, invalid values, refusal of unknown options.
- Experiment: registry discovery, option validation, text and JSON output.
- Benchmark: timings distribution, runner repetition counts, environment
  fields, all three formatters.

---

## 8. Phases 2–8 — Chapters 01–07

Every chapter follows the same definition of done:

1. Implementation plus a naive baseline or oracle.
2. Unit, differential and property tests; invariants.
3. Three to five experiments under `experiments/NN-topic/`.
4. A benchmark suite in `benchmarks/`.
5. A results table in `docs/BENCHMARKS.md` and conclusions.
6. Decision entries for non-trivial trade-offs.
7. Book material: code excerpts with file and line ranges, experiment
   commands, real output, two or three failure modes.
8. `make test analyse format-check` green; this document updated.
9. One logical commit plus review fix-ups.

### Phase 2 — Chapter 01: Hash Table and Bloom Filter — code and measurements complete

**Status (2026-10-22):** the implementation, tests, experiments and benchmark
suites are complete. The book excerpt is deferred to Phase 11, where every
chapter is written against a tagged release. The stretch tables were not built.

**Code:** `src/HashTable/ChainingHashTable.php`,
`src/HashTable/OpenAddressingHashTable.php` with linear, quadratic (triangular)
and double probing, tombstones and resize; `src/HashTable/HashFunction.php`
for named hash functions; `src/HashTable/Entry.php`;
`src/BloomFilter/BloomFilter.php` with optimal `m`/`k` and double hashing.
Robin Hood hashing and Counting Bloom were left as optional stretch work.

**Expected complexity:** put/get/remove O(1) average, O(n) worst; Bloom
add/check O(k), memory O(m).

**Experiments:** `hash:load-factor` at α = 0.5/0.75/0.9 with hit and miss
probes; `hash:collisions` across FNV-1a, xxh128, CRC32 and Murmur3;
`hash:memory` against a PHP array; `bloom:fpr` at p = 1/5/10% with the
estimated rate and optimal k. Amortized-rehash and Bloom-versus-hash-set
experiments remain optional extensions.

**Benchmark suites:** `hash-table` (chaining/open-addressing get and 1,000-key
insert) and `bloom` (add, hit-check, miss-check). The development run uses
10,000 keys; the 1e6-key sweep belongs to the book run, not the test suite.

**Result:** 133 tests and 19,593 assertions pass; PHPStan level 8 reports no
errors over 70 files; PHP-CS-Fixer reports no changes; both JSON experiment and
benchmark outputs validate. The measured tables and conclusions are in
[docs/BENCHMARKS.md](BENCHMARKS.md). The main findings are a 116-probe linear
miss at α = 0.90, open addressing at 81 bytes/key versus chaining at 155.9,
and Bloom false-positive rates of 0.0118 / 0.0520 / 0.1059 for targets
0.01 / 0.05 / 0.10.

**Book:** why a PHP array is already a hash table; chaining versus open
addressing; the price of collisions; false positives and memory. Deferred to
the book phase.

### Phase 3 — Chapter 02: LRU / LFU Cache — code and measurements complete

**Status (2026-10-22):** the implementation, tests, experiments and benchmark
suite are complete. The book excerpt is deferred to Phase 11, where every
chapter is written against a tagged release. Segmented LRU and TinyLFU were
not built.

**Code:** `src/Cache/Cache.php` for the shared contract; `CacheStats` for
hits, misses and evictions; `LRUCache` with a map and sentinel doubly linked
list; `LFUCache` with one frequency bucket per count, a `minFrequency` pointer
and an LRU tie-break inside each bucket; `HeapLFU` with a min-heap, immutable
priority records and lazy deletion; `NaiveLRU` as the O(n) list baseline;
`ArrayLRU` as the PHP insertion-order baseline. `src/Foundation/ZipfDistribution.php`
provides the deterministic Zipf sampler, and `Counters` gained `relinks` for
pointer maintenance.

**Expected complexity:** LRU and LFU get/put/remove are O(1) expected (the map
lookup is average O(1)); HeapLFU is O(log n) per access/eviction plus lazy
record cleanup; NaiveLRU is O(n) per lookup and O(n) eviction because it shifts
the list; ArrayLRU is average O(1) in PHP but depends on the engine's array
implementation.

**Experiments:** `cache:zipf` sweeps Zipf α = 0.8/1.0/1.2 at capacities
100/1k/10k; `cache:scan` warms a popular set and flushes it with a sequential
scan; `cache:loop` covers loop lengths capacity−1/capacity/capacity+1/2×capacity;
`cache:shift` moves popularity from A to B and back; `cache:memory` reports
bytes per entry for LRU, LFU, HeapLFU, ArrayLRU, NaiveLRU and a PHP array.

**Benchmark suite:** `cache` — LRU/LFU/HeapLFU/ArrayLRU/NaiveLRU get hits and
put-with-eviction, plus a PHP-array get baseline.

**Result:** 206 tests and 26,197 assertions pass; PHPStan level 8 reports no
errors over 96 files; PHP-CS-Fixer reports no changes. The measured tables and
conclusions are in [docs/BENCHMARKS.md](BENCHMARKS.md). Main findings: LFU wins
on stable Zipf popularity but fails after a popularity shift; LRU flushes on
both a sequential scan and a `capacity + 1` loop; ArrayLRU matches LRU's hit
ratio with 66.6 bytes/entry versus 192.5 for linked-list LRU; NaiveLRU is
roughly two orders of magnitude slower on hit lookups at 10,000 entries.

**Book:** O(1) pointer work versus hit ratio; LFU without decay; scan pollution
and the loop pathology; capacity 0/1 edges; why PHP's array is a serious
baseline. Deferred to the book phase.

### Phase 4 — Chapter 03: Graph Algorithms — code and measurements complete

**Status (2026-10-22):** the implementation, tests, experiments and benchmark
suite are complete. The book excerpt is deferred to Phase 11, where every
chapter is written against a tagged release. A*, Tarjan SCC, PageRank and a
Barabási–Albert generator were not built.

**Code:** `src/Graph/Graph.php` and `MutableGraph` for the simple-graph
contract; `AdjacencyList`, `AdjacencyMatrix`, `EdgeList` and `CsrGraph` for
the four representations; `Bfs`, `Dfs`, `Components`, `TopologicalSort`,
`Dijkstra`, `DijkstraDecreaseKey`, `SplDijkstra`, `BellmanFord`, `Kruskal` and
`Prim` for the algorithms; `GraphGenerator` for path, star, grid, complete,
random and random-DAG edge lists. `src/Foundation/IndexedMinHeap.php` provides
the decrease-key heap, and `UnionFind` now exposes path-compression and
union-by-rank flags plus optional `Counters`.

**Expected complexity:** BFS/DFS and topological sort O(V + E); Dijkstra
O((V + E) log V) with either heap strategy, with the lazy heap allowed more
than V entries; Bellman-Ford O(VE); Kruskal O(E log E + E α(V)); Prim
O(E log V); UnionFind O(α(V)) amortized with both optimizations. Adjacency
list is O(V + E) memory, matrix O(V²), edge list O(E), CSR O(V + E).

**Experiments:** `graph:representations` compares memory, build and BFS reach
for list/matrix/edge-list/CSR at 1,000 vertices and 4,000 edges;
`graph:traversal` compares BFS and iterative DFS on sparse/denser graphs;
`graph:dijkstra` compares lazy, decrease-key, SplPriorityQueue and Bellman-Ford;
`graph:topological-sort` validates Kahn and DFS on a random DAG and a cycle;
`graph:dsu` isolates compression and rank on a 10,000-element chain.

**Benchmark suite:** `graph` — BFS/DFS sparse and dense, Dijkstra lazy/
decrease-key/Spl, Bellman-Ford, Kahn/DFS topological sort, Kruskal and Prim.

**Result:** 255 tests and 26,739 assertions pass; PHPStan level 8 reports no
errors over 131 files; PHP-CS-Fixer reports no changes. The measured tables and
conclusions are in [docs/BENCHMARKS.md](BENCHMARKS.md). Main findings: an
adjacency matrix used 16.0 MiB for 4,000 edges versus 303.1 KiB for CSR; BFS
and DFS each read exactly `2E` arcs; the lazy Dijkstra popped 894 entries for
500 vertices while decrease-key popped 500; the naive union-find did 9,999
node reads per find versus 1 for the optimized variants; and the C-level
SplPriorityQueue was faster than the PHP heaps despite the same asymptotic
class. No multi-size time fit is claimed.

**Book:** why representation matters more than the algorithm sometimes; heap
cost; negative edges; disconnected graphs and self-loops; the difference
between an amortized union-find bound and one expensive find. Deferred to the
book phase.

### Phase 5 — Chapter 04: B-Tree and Search — code and measurements complete

**Status (2026-10-22):** the implementation, tests, experiments and benchmark
suite are complete. The book excerpt is deferred to Phase 11, where every
chapter is written against a tagged release. B+ deletion was not built.

**Code:** `src/Tree/BTree.php` with `BTreeNode`: minimum-degree B-tree search,
top-down insertion with splits, pre-emptive deletion with borrow/merge, root
collapse, sorted traversal, height and invariant validation.
`BPlusTree.php` with `BPlusNode`: point search, recursive insertion, linked
leaves, range scan and sorted bulk load. `PageSimulator` is the 4/8 KiB page
model; `SortedArrayIndex` and `PhpArrayIndex` are the flat baselines;
`KeyGenerator` supplies reproducible shuffled, ascending and descending key
sets.

**Expected complexity:** B-tree and B+ search/insert/delete O(log_t n) node
reads, with O(t) key work per node; B+ range O(log_t n + k); bulk load O(n);
sorted-array search O(log n) but insert/delete O(n); PHP-array operations
expected O(1). Memory is O(n) for all structures.

**Experiments:** `tree:height` compares t = 4/8/16/32 at 50,000 keys and has a
separate one-million-key run; `tree:insert-patterns` compares random, ascending
and descending insertion plus B+ bulk load; `tree:range` measures B+ ranges of
10/100/1,000 keys; `tree:page-reads` compares the page model with binary-search
comparisons at 4/8 KiB; `tree:memory` reports bytes/key for the B-tree, bulk
B+ tree, sorted array and PHP array.

**Benchmark suite:** `tree` — B-tree/B+ search, B-tree/B+ batch insertion, B+
bulk load, B-tree build-plus-delete and B+ range scan. The flat sorted array
and PHP array are point-search baselines.

**Result:** 279 tests and 32,999 assertions pass; PHPStan level 8 reports no
errors over 149 files; PHP-CS-Fixer reports no changes. The measured tables and
conclusions are in [docs/BENCHMARKS.md](BENCHMARKS.md). Main findings: at one
million keys the B-tree height fell from 9 at t = 4 to 4 at t = 32; at 50,000
keys bytes/key fell from 87.4 to 33.5; a bulk-loaded B+ tree used 37.4
bytes/key versus 50.1 for the inserted B-tree and about 21 for the flat
baselines; ranges of 10/100/1,000 keys cost 4.29/7.22/36.28 node reads; and the
page model charged 3.95 pages per tree search versus 16.68 binary-search
comparisons. No multi-size time fit is claimed.

**Book:** why a B-tree is about blocks, not CPU; fanout and height; why binary
search loses when each probe is an I/O; sorted insertion is not automatically a
better shape; deletion rebalancing; and why a B+ linked-leaf range scan is a
different operation from point lookup. Deferred to the book phase.

### Phase 6 — Chapter 05: Consistent Hashing — code and measurements complete

**Status (2026-10-23):** the implementation, tests, experiments and benchmark
suite are complete. The book excerpt is deferred to Phase 11, where every
chapter is written against a tagged release. Jump consistent hashing, weighted
nodes and data replication were not built.

**Code:** `src/ConsistentHash/NodeHash.php` is the shared contract;
`ModuloHash` is the remapping baseline; `HashRing` owns sorted virtual-token
positions, binary-search lookup, collision probing and `getNodes()` for replica
order; `RendezvousHash` is the highest-random-weight alternative. All strategies
use the same xxh128-based hash.

**Expected complexity:** ring lookup O(log V) over V total virtual tokens; ring
add/remove O(V log V) in this sorted-list implementation; rendezvous lookup
O(N); modulo lookup O(1); memory O(V + N) for the ring and O(N) for the two
node-list baselines. Adding or removing one node should move about
`1/(N + 1)` of keys for a consistent strategy, versus about `N/(N + 1)` for
modulo.

**Experiments:** `ring:remap` measures add/remove fractions against the ideal;
`ring:balance` sweeps 1/10/100/500 virtual nodes and reports max/mean,
coefficient of variation and a chi-square relative score; `ring:replicas`
checks R distinct owners and the fallback after the primary node fails.

**Benchmark suite:** `ring` — ring/modulo/rendezvous lookup, three-replica
lookup, and add/remove for each strategy.

**Result:** 294 tests and 33,212 assertions pass; PHPStan level 8 reports no
errors over 160 files; PHP-CS-Fixer reports no changes. The measured tables and
conclusions are in [docs/BENCHMARKS.md](BENCHMARKS.md). Main findings: modulo
remapped 91.01% of keys on an add, while the ring and rendezvous remapped
9.61%/10.13% and 9.03%/9.09% against a 9.09% ideal; 20 nodes and 100 virtual
nodes per node reduced max/mean from 1.904 at one token to 1.201 and the
coefficient of variation from 0.565 to 0.107; 500 tokens reached 1.068 and
0.042; and the ring moved all 2,082 affected keys to the old second replica
when `node-2` failed. Ring lookup was 1,448,252 ops/sec, modulo 3,321,840 and
rendezvous 196,098 in the fixed benchmark. No multi-size time fit is claimed.

**Book:** why modulo breaks a cache; the cost of virtual nodes; ring versus
rendezvous; what "uniform" actually means; fallback order is not replication.
Deferred to the book phase.

### Phase 7 — Chapter 06: Top-K and Stream Processing — code and measurements complete

**Status (2026-10-23):** the implementation, tests, experiments, benchmark and
child-process memory test are complete. The book excerpt is deferred to Phase
11, where every chapter is written against a tagged release. A sliding window,
exponential decay and mergeable summaries were not built.

**Code:** `src/Stream/TopKHeap.php` is an exact indexed heap of K entries plus
a count map for every distinct key; `QuickSelect` is the exact batch
alternative; `CountMinSketch` is a fixed-size estimator with no key
enumeration; `SpaceSaving` and `MisraGries` are bounded candidate summaries;
`StreamGenerator` produces reproducible uniform, Zipf, all-unique and drift
streams.

**Expected complexity:** exact top-K needs O(D + K) memory for D distinct keys,
not O(K); heap updates are expected O(1) map work plus O(log K) repair.
QuickSelect is average O(D), worst O(D²), and needs the full map. Count-Min is
O(d) per update and O(w × d) memory. Space-Saving and Misra-Gries are O(k)
memory, with an O(k) scan on the slow path. Adding one item is not a proof of
the asymptotic constants; the tables are one fixed workload.

**Experiments:** `stream:topk` compares the exact heap with fixed-size
selection; `stream:heavy-hitters` reports detector recall/precision/rank error
and estimator error at equal counter budgets; `stream:all-unique` shows the
exact map growing while summaries stay bounded; `stream:drift` switches the hot
set halfway through the stream.

**Benchmark suite:** `stream` — exact heap observe/batch, QuickSelect,
Count-Min, Space-Saving and Misra-Gries throughput.

**Result:** 334 tests and 33,520 assertions pass; PHPStan level 8 reports no
errors over 179 files; PHP-CS-Fixer reports no changes. On 100,000 Zipf items
with 7,348 distinct keys, heap updates at K=10/100/1,000 took
22.910/23.259/31.691 ms and used 344.0/338.2/506.0 KiB including the full count
map; QuickSelect took 2.781/2.978/3.441 ms. A 100,000-key all-unique stream cost
5,166.7 KiB exact versus 169.2 KiB for Count-Min, 18.8 KiB for Space-Saving and
17.0 KiB for Misra-Gries. Detector recall at K=10/100/1,000 was
50.0%/48.0%/55.9% for Space-Saving and 30.0%/46.0%/56.4% for Misra-Gries;
Count-Min's mean error on the true top K fell from 5,554.60 to 353.77 to 14.52
as its width grew, while Space-Saving's error fell from 252.70 to 3.64 to
0.018. The drift run kept ten stale phase-a keys in both summaries while
recalling all 20 current keys. In the fixed benchmark, persistent heap updates
ran at 3,628,457 items/sec, Count-Min at 948,145, Space-Saving at 806,782 and
Misra-Gries at 8,409,338. The 8M child process proved the exact map fails while
Count-Min and Space-Saving complete. No multi-size time fit is claimed.

**Book:** bounded memory; why exact top-K is not a K-sized heap; Count-Min as
an estimator versus candidate summaries; detector recall versus frequency
error; concept drift is not recency. Deferred to the book phase.

### Phase 8 — Chapter 07: External Sorting — code and measurements complete

**Status (2026-10-23):** the implementation, tests, experiments, benchmark and
child-process memory test are complete. The book excerpt is deferred to the
book phase. There is no replacement-selection fast path, compression or
parallel merge; those are out of scope for this chapter.

**Code:** `ExternalSorter` reads through `ChunkReader`, sorts each byte-bounded
chunk with the configured comparator, spills sorted runs with `tempnam()`, and
merges at most `fanIn` runs through a `BinaryHeap`. `LineReader` streams input
lines, `MergeEntry` carries the run number used for stable ties, `SortStats`
records runs/passes/logical bytes/comparisons, and `DatasetGenerator` plus
`DatasetCache` provide deterministic 76-byte random, sorted, reversed and
duplicate-heavy inputs. Temporary runs are removed in a `finally` block. The
final merge is counted as a pass even for one run, so the stats match the copy
the code performs.

**Expected complexity:** with N records, chunk C, R initial runs and fan-in F,
chunk sorting is O(N log C); each merge pass is O(N log F) comparisons and one
read plus one write of N; the implementation performs
`max(1, ceil(log_F R))` passes. Live memory is O(C + F). A larger chunk removes
runs but raises the PHP array footprint; a larger fan-in removes passes but
opens more files. The 128 MiB raw chunk measured 291.4 MiB PHP peak, so it does
not fit the 128M case; the 500 MiB / 128M run uses a 32 MiB chunk.

**Experiments:** `sort:memory-limit` (100 MiB, chunks 8/32/128 MiB, fan-in 8);
`sort:fan-in` (100 MiB, 8 MiB chunk, fan-in 2/8/32/128); `sort:patterns`
(10,000 records, random/sorted/reversed/duplicate, full-output checksum against
stable in-memory `usort`); `sort:io` (100 MiB default, 500 MiB with
`--input-mb=500 --chunk-mb=32 --fan-in=32`, including a direct
`-d memory_limit=128M` run).

**Benchmark suite:** `external-sort` — 8 MiB input, 1 MiB and 4 MiB chunks,
fan-in 2/4/16, five repetitions.

**Result:** 355 tests and 33,786 assertions pass; PHPStan level 8 reports no
errors over 196 files; PHP-CS-Fixer reports no changes. On the 100 MiB / 1,379,705
record input, chunks 8/32/128 MiB took 5.389/3.954/3.190 s, used 600/400/400 MiB
of logical I/O and peaked at 29.6/118.3/291.4 MiB. With an 8 MiB chunk and 13
initial runs, fan-in 2/8/32/128 produced 4/2/1/1 passes and 6.370/5.320/4.985/
4.647 s. A 500 MiB / 6,898,526 record input with a 32 MiB chunk and fan-in 32
finished in 27.201 s at 512M and 26.843 s at 128M; both peaked at 118.4 MiB and
moved 2,000 MiB of logical I/O. The pattern checksums all matched; the 10,000-
record runs took 79-96 ms externally versus 9-13 ms in memory. The external-sort
benchmark medians were 435.965/221.379/421.688/336.893 ms for chunk-1m,
chunk-4m, fan-in-2 and fan-in-16. The 8M child test sorted an 8 MiB input with a
512 KiB chunk, while `file()` + `usort()` died at the same limit. No multi-size
time fit is claimed.

**Book:** the external-memory model; why k-way merge rather than two-way; the
cost of an extra pass; open-file limits; chunk size as a PHP-memory budget, not
a nominal byte count; stability as chunk order plus run order; why in-memory
`sort()` is not a solution. Deferred to the book phase.

---

## 9. Phase 9 — Integration scenarios — MVP complete

The topics must not remain isolated exercises. Three scenarios connect them to
the sibling systems:

1. **Cache tier.** Consistent hashing + LRU/LFU: an N-node cluster, node
   addition and failure, hit ratio and key movement. Links to php-mini-cache
   and php-systems-platform. Not implemented yet.
2. **Log pipeline.** Access-log stream → Bloom for uniqueness → Top-K URLs →
   external sort for a daily report. Links to the observability chapters.
   ✅ MVP complete.
3. **Mini index.** B+Tree + Bloom: point queries and the savings from false
   positives versus a full scan. Links to php-mini-database and
   php-mini-search. Not implemented yet.

The MVP needs one scenario. The original plan set two or three for v1.0; this
release takes the MVP gate (one) and defers the other two rather than blocking
the book on them. The log pipeline was chosen because it composes three
chapters rather than re-testing one. The cache-tier and mini-index scenarios
remain future work, not hidden partial implementations.

**Status (2026-10-26):** complete at code commit `4fe7adb`. `make scenario` is
the one-command entry point; it delegates to
`php bin/experiment scenario:log-pipeline` so the experiment discovery and
option validation stay in one harness. The scenario writes both the terminal
report and `var/results/log-pipeline-report.txt`.

**Code:** `src/Integration/LogStream` yields a deterministic Zipf URL stream;
`LogPipeline` uses Bloom only as a first-seen detector, inserts only candidates,
keeps exact counts in `TopKHeap`, and writes `count<TAB>url` records through
`ExternalSorter`; `LogPipelineResult` carries Bloom counters, the exact top-K,
report I/O and timings. `LogPipeline` sorts by count descending and URL
ascending with a 32 KiB chunk and fan-in 8. Bloom false positives are measured
as `exact distinct - new candidates`; the exact distinct count comes from
`TopKHeap`'s count map, so no separate validation set is needed.

**Result:** 100,000 requests over 20,000 Zipf ranks (seed `book`) produced
10,442 exact distinct URLs. Bloom used 191,702 bits (23,963 B), 7 hashes and
inserted 10,440 candidates; two first occurrences were false positives (0.019%
observed versus a 0.032% formula estimate and a 1% target, because the filter
was sized for 20,000 URLs and the realized distinct count was lower). Ingest
took 0.246 s with a 1.79 MiB PHP peak; the report had 10,442 records, 10 runs
and 2 merge passes, read/wrote 948,765 B each (1,897,530 B logical I/O), used
64,198 comparisons and 0.088 s, with a 0.45 MiB sort peak. The full report is
316,255 B. The top three URLs had 14,608, 6,836 and 4,295 requests. These are
one-run integration observations, not benchmark medians.

**Tests:** 361 tests and 34,314 assertions; PHPStan level 8 no errors over 202
files; PHP-CS-Fixer 202 files clean. The integration tests cover Bloom's
candidate equation, exact top-K, count-tie report order, empty input and the
deterministic URL stream. The external sorter and top-K internals retain their
chapter tests.

**Definition of done:** one command runs the scenario, records its own report
and shows the effect of each structure. The cache-tier and mini-index scenarios
are deferred; they do not block the v1.0 release checklist below.

---

## 10. Phase 10 — Release and book handoff

- Tag `v1.0.0` after all seven chapters and the integration scenario.
- Pin every code block in the book to the tag or a full SHA.
- Extend the book checker with `--expected-chapters=7`.
- Add the Algorithms Lab check to the book CI.
- Update `book/algorithms-lab/README.md` and the series README; remove the
  draft status.
- All `docs/PHASES.md` phases marked complete; all tables in
  `docs/BENCHMARKS.md`.

---

## 11. Testing and quality

- **Unit:** boundaries, empty structures, one element, duplicates, deletion.
- **Differential/property:** random operations against a reference model
  (PHP array, sorted array, naive LRU, Bellman-Ford).
- **Invariants:** B-tree order and balance, ring contents after add/remove,
  heap order, sizes, tombstones.
- **Memory-limit tests:** a separate process with a lowered limit for external
  sort and bounded-memory sketches.
- **Benchmark smoke:** tiny sizes in CI; asserts that the harness runs and
  counts, not that a time threshold was met.
- **DocumentationTest:** every doc is linked from the README, relative links
  resolve, backtick paths exist, and documented experiment/benchmark commands
  exist.
- **CI:** `make build install test analyse format-check`, then `make down`.
  Performance runs stay manual.
- **Mutation testing** (Infection) is a stretch after stabilization.
- One phase equals one logical commit; status is complete only after the
  container is green. Review fixes are separate commits.

---

## 12. Reproducibility and statistics

- Seed, commit and environment travel with every result.
- Benchmarks use warm-up plus repetitions; report median, min/max, p95 and MAD;
  ops/sec comes from the median.
- Memory is measured in a separate process with a baseline,
  `memory_get_usage(false)` and peak.
- Xdebug is off; OPcache/JIT and `memory_limit` are recorded.
- Deterministic counters need one run, but the spread is still shown.
- Complexity estimates use a log-log fit; "looks like O(n log n)" is supported
  by the exponent and R², not by intuition.
- Docker on macOS adds virtualization noise. Book numbers are taken in a fixed
  Linux environment; every report includes the environment block and says that
  the numbers describe that machine.
- Results are written to
  `var/results/<date>-<commit>/meta.json`, `raw.csv` and `summary.md`.
- Data comes from seeded generators; large files live in `var/datasets`
  (gitignored) with checksums; small fixtures (< 5 MB) live in `datasets/` and
  their licenses are recorded.

---

## 13. Documentation, book and release

| Where | What | Language |
|---|---|---|
| `README.md` | What it is, how to run, chapter map, docs, structure, roadmap | EN |
| `docs/PHASES.md` | Build journal: Goal / Tasks / DoD / Tests per phase | EN |
| `docs/DECISIONS.md` | Dated decisions and trade-offs | EN |
| `docs/BENCHMARKS.md` | Method, environment, result tables | EN |
| `docs/ARCHITECTURE.md` | Module and harness map | EN |
| `docs/COMPLEXITY.md` | Theory versus measured behavior | EN |
| `book/algorithms-lab/NN-*.md` | Narrative, code, experiment, failures, exercises | RU |
| `book/algorithms-lab/README.md` | Table of contents, reading paths, status | RU |

### Book chapter template

1. **Problem** — why the mechanism exists and what breaks without it.
2. **Design** — how it works, with a diagram.
3. **Code** — excerpts from real files, each with a GitHub link to a tag or SHA
   and a line range.
4. **Experiment** — `make experiment ARGS="..."`, real output.
5. **Failures** — what breaks and how the project catches it.
6. **How it is verified** — concrete tests and why they can be trusted.
7. **Numbers** — a table from `make benchmark` with the environment block.
8. **Exercises** — three to five problems on understanding and calculation.
9. **What next** — connection to the next chapter and the sibling labs.

### Code-block contract

- Code in a chapter is copied verbatim from a pinned SHA or tag.
- A GitHub permalink follows each block.
- The book checker compares the block with the source; any difference fails CI.
- During writing, use the SHA of the pushed commit; after release, replace it
  with `v1.0.0` (a SHA is also acceptable; a branch link is not).
- Code blocks in the book are never edited by hand after copying.

### Book repository changes

- Add `--expected-chapters` to the checker, defaulting to 26 for backward
  compatibility.
- Add a step to the book CI:
  `python3 ../php-systems-lab/tools/check_book.py --expected-chapters=7`.
- Local run from `book/algorithms-lab`:
  `python3 ../php-systems-lab/tools/check_book.py --source-root ../.. --expected-chapters=7`.
- Update the book and series READMEs.
- Moving the checker to `book/tools/` is an optional refactor, not a blocker.

### Release checklist

- [ ] All seven chapters implemented; experiments and benchmarks reproduce.
- [ ] `make test analyse format-check` green in the container.
- [ ] All phases complete; all benchmark tables present.
- [ ] README has the chapter map, book links, docs and structure.
- [ ] Public repository and CI green.
- [ ] `v1.0.0` tag pushed.
- [ ] All book links pinned to the tag.
- [ ] Book checker with seven chapters green.
- [ ] Book README no longer says "draft".
- [ ] Optional announcement article.

---

## 14. Roadmap

Estimates assume part-time work (~10–12 hours/week), excluding writing the
book.

| Phase | Content | Estimate | Artifact |
|---|---|---|---|
| 0 | Ecosystem scaffold, Docker, CI, docs, DocumentationTest | 1 week | `make check` green, first commit |
| 1 | Foundation + experiment/benchmark harness | 2 weeks | demo experiment + demo benchmark |
| 2 | Chapter 01: Hash + Bloom | 2 weeks | hash-table, bloom suites |
| 3 | Chapter 02: LRU/LFU | 2 weeks | cache suite |
| 4 | Chapter 03: Graphs | 3 weeks | graph suite |
| 5 | Chapter 04: B-Tree | 2.5 weeks | tree suite |
| 6 | Chapter 05: Consistent Hashing | 1.5 weeks | ring suite |
| 7 | Chapter 06: Top-K / streams | 2 weeks | stream suite |
| 8 | Chapter 07: External Sorting | 2 weeks | external-sort suite |
| 9 | Integration scenarios + benchmark pass | 2 weeks | 2–3 scenarios, report |
| 10 | Release v1.0.0 + book handoff (repo side) | 1 week | tag, README, checker |
| 11 | Write seven chapters in the book | 4–6 weeks | chapters + green checker |

Repository only: ~19–20 weeks. With the book: ~24–26 weeks.

**MVP (v0.1):** Phases 0–8, one solid implementation per chapter, one or two
experiments, one benchmark suite, docs and DocumentationTest; no integration,
real datasets or stretch goals. ~15–17 weeks.

**v1.0:** MVP + Phases 9–10 + book + real data + alternative implementations.

The chapter order is fixed by the book. If faster feedback is needed, build
thin slices of all seven topics first (MVP alternative), but write the chapters
one at a time and in book order.

---

## 15. Risks and mitigations

| Risk | Consequence | Mitigation |
|---|---|---|
| Book code drifts from the repository | The book lies | Pinned SHA/tag + checker in CI |
| Docker on macOS distorts benchmarks | Wrong conclusions | Environment block, relative comparisons, Linux/CI numbers for the book |
| PHP overhead hides algorithm differences | False conclusions | Operation counters as the primary metric; minimize objects per element |
| Noisy time benchmarks | Invented speedups | Warm-up, repetitions, median/p95, CV, recorded environment |
| Rough PHP memory measurement | Wrong bytes/key | Separate process, baseline, `memory_get_usage(false)`, caveats |
| Scope creep (seven chapters + book) | The project never ends | MVP, one chapter at a time, explicit stretch list |
| Docs go stale | README lies | DocumentationTest in CI |
| Book links to a missing tag | Book CI is red | Tag before switching links; SHA during writing |
| One repository versus seven | Book links | Decision recorded; change only before writing |
| Huge datasets | Heavy repository | Generators, `var/datasets`, gitignore, checksums |
| Xdebug in the container | Skewed measurements | `mode=off` by default; explicit debug targets |
| Flaky performance in CI | Red builds | Smoke only in CI; no timing thresholds |
| Motivation loss on graphs/B-trees | Abandoned project | Alternate heavy and light chapters; integration as the reward |

---

## 16. MVP and stretch

### MVP

- Phases 0–8.
- One implementation per topic plus a baseline.
- One or two experiments per topic and one benchmark suite.
- Differential and property tests.
- README and docs (PHASES, DECISIONS, BENCHMARKS, ARCHITECTURE, COMPLEXITY).
- DocumentationTest.
- One integration scenario (the minimum of Phase 9).

### Stretch

- Robin Hood hashing, counting and scalable Bloom filters.
- TinyLFU and segmented LRU.
- Jump consistent hash.
- B+tree bulk load, skip list.
- A*, Tarjan SCC, PageRank, CSR optimizations.
- Sliding-window top-K, t-digest.
- Radix sort for external sort, chunk compression.
- Charts and a static HTML report.
- Comparison with an implementation in another language.
- Mutation testing, fuzzing.
- A full set of real datasets.

---

## 17. Resolved decisions

1. **One repository, not seven.** The book has one Algorithms Lab README and
   the topics share the harness. Recorded in `docs/DECISIONS.md`.
2. **Public repository.** `Researcher86/algorithms-lab` exists; CI is green.
3. **Book timing.** Write the code first, tag v1.0.0, then write and pin the
   chapters.
4. **Real data.** Start with seeded generators and small fixtures; add real
   datasets only when a chapter needs one and the image can carry it.
5. **Reference machine.** Book numbers come from one fixed Linux environment
   (CI or a dedicated host); local macOS Docker numbers are for development.
6. **Language.** Engineering docs and code are English; the book is Russian.
7. **Scope.** MVP ~15–17 weeks; full v1.0 ~20 weeks without the book.

The next action is Phase 1: build the foundation, the instrumentation, and the
two entry points, then run the demo experiment and benchmark in the container.
