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
`tree:height`, `ring:remap`, `stream:top-k`, `sort:memory-limit`.

---

## 5. Chapter ↔ module ↔ experiment ↔ benchmark map

| # | Book chapter | Module | Core code | Experiments | Benchmark suite |
|---|---|---|---|---|---|
| 01 | Hash Table and Bloom Filter | HashTable, BloomFilter | ChainingHashTable, OpenAddressingHashTable, BloomFilter | hash:load-factor, hash:collisions, hash:memory, bloom:fpr | hash-table, bloom |
| 02 | LRU / LFU Cache | Cache | LRUCache, LFUCache, NaiveLRU | cache:zipf, cache:scan, cache:loop, cache:lfu-shift | cache |
| 03 | Graph Algorithms | Graph | AdjacencyList, CsrGraph, Bfs, Dfs, Dijkstra, Kruskal, UnionFind | graph:representations, graph:dijkstra, graph:topo, graph:dsu | graph |
| 04 | B-Tree and Search | Tree | BTree, BPlusTree, PageSimulator | tree:height, tree:range, tree:page-reads, tree:insert-patterns | tree |
| 05 | Consistent Hashing | ConsistentHash | HashRing, RendezvousHash, ModuloHash | ring:remap, ring:balance, ring:replicas | ring |
| 06 | Top-K and Stream Processing | Stream | TopKHeap, QuickSelect, CountMinSketch, SpaceSaving | stream:top-k, stream:sketch, stream:unique, stream:drift | stream |
| 07 | External Sorting | ExternalSort | ExternalSorter, ChunkReader, KWayMerge | sort:memory-limit, sort:fan-in, sort:patterns, sort:io | external-sort |

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

### Phase 2 — Chapter 01: Hash Table and Bloom Filter

**Code:** ChainingHashTable, OpenAddressingHashTable with linear, quadratic and
double probing, tombstones and resize; RobinHoodHashTable as a stretch;
BloomFilter with `optimal(n, p)` and double hashing; CountingBloomFilter as a
stretch.

**Expected complexity:** put/get/remove O(1) average, O(n) worst; Bloom
add/check O(k), memory O(m).

**Experiments:** load factor α = 0.5/0.75/0.9 and probes on hit/miss;
collision-heavy keys across hash functions; bytes per key against a PHP array;
amortized rehash; measured Bloom false-positive rate against p = 1/5/10% and
the optimal k; Bloom versus a hash set at the same false-positive rate.

**Benchmark suites:** hash-table (insert/get/delete at N = 1e3…1e6, chaining
versus open addressing, α sweep) and bloom (add/check, N and p sweep).

**Book:** why a PHP array is already a hash table; chaining versus open
addressing; the price of collisions; false positives and memory.

### Phase 3 — Chapter 02: LRU / LFU Cache

**Code:** LRUCache (map + doubly linked list), LFUCache (frequency buckets +
minFreq, LRU tie-break), NaiveLRU (O(n)), ArrayLRU (PHP array order), HeapLFU
(O(log n)); segmented LRU and TinyLFU as stretch.

**Experiments:** hit ratio on Zipf(α = 0.8/1.0/1.2) at capacities 100/1k/10k;
a sequential scan flushing LRU; a loop of length capacity + 1; popularity
shift; bytes per entry.

**Benchmark suite:** cache — get/put/evict, LRU versus LFU versus naive versus
array.

**Book:** O(1) on pointers; why LFU without recency decays; scan pollution;
capacity 0/1 edge cases.

### Phase 4 — Chapter 03: Graph Algorithms

**Code:** AdjacencyList, AdjacencyMatrix, EdgeList, CsrGraph; BFS, iterative
DFS, components, topological sort (Kahn and DFS), cycle detection; Dijkstra
with a custom heap and lazy deletion versus decrease-key; Bellman-Ford;
Kruskal + UnionFind; Prim; A*, Tarjan SCC and PageRank as stretch; ER, grid,
BA, path, star and complete generators.

**Experiments:** memory of list/matrix/CSR at several V/E; BFS/DFS on sparse
versus dense graphs; Dijkstra with a custom heap versus SplPriorityQueue and
lazy versus decrease-key; Kahn versus DFS topological sort; DSU with and
without path compression and union by rank; Dijkstra versus Bellman-Ford on
random graphs.

**Benchmark suite:** graph — BFS/DFS/Dijkstra/topological sort at different
densities.

**Book:** why representation matters more than the algorithm sometimes; heap
cost; negative edges; disconnected graphs, self-loops and parallel edges.

### Phase 5 — Chapter 04: B-Tree and Search

**Code:** BTree of order t with search/insert/delete, borrow/merge/split;
BPlusTree with range scan, linked leaves and bulk load; PageSimulator with
4/8 KiB pages and node-read counters; baselines: sorted array + binary search
and a PHP array.

**Experiments:** height and node reads for N = 1e3…1e6 and fanout; random
versus sorted inserts and bulk load; range sizes 10/100/1000; simulated page
reads versus binary search; bytes per key.

**Benchmark suite:** tree — search/insert/delete/range.

**Book:** why a B-tree is about blocks, not CPU; fanout and height; why binary
search loses on disk; deletion rebalancing.

### Phase 6 — Chapter 05: Consistent Hashing

**Code:** ModuloHash baseline, HashRing with sorted positions, virtual nodes,
binary search, add/remove/getNodes; RendezvousHash; JumpConsistentHash as
stretch.

**Experiments:** remapped-key fraction versus 1/(N+1); max/mean, CV and
chi-square at 1/10/100/500 virtual nodes; R consecutive nodes and node failure;
lookup throughput as nodes and vnodes grow.

**Benchmark suite:** ring — lookup, add/remove.

**Book:** why modulo breaks a cache; the cost of vnodes; ring versus HRW; what
"uniform" actually means.

### Phase 7 — Chapter 06: Top-K and Stream Processing

**Code:** TopKHeap O(n log k), QuickSelect O(n) average, CountMinSketch,
SpaceSaving/Misra-Gries; sliding window as stretch; uniform, Zipf, all-unique
and drift generators.

**Experiments:** recall/precision and rank error at K = 10/100/1000; Count-Min
versus Space-Saving at equal memory; an all-unique stream where exact counting
exceeds memory but a sketch does not; popularity drift in the middle of a
stream.

**Benchmark suite:** stream — exact versus sketch, throughput, memory.

**Book:** bounded memory; ε/δ; why exact top-K on a stream is a memory
question, not a heap of size K; concept drift.

### Phase 8 — Chapter 07: External Sorting

**Code:** ExternalSorter (chunk → sort → spill → k-way merge through a heap),
ChunkReader over generators, configurable chunk size and fan-in, comparator,
stability, cleanup of temporary files.

**Experiments:** 100/500 MB of input under `memory_limit=128M` with chunks of
8/32/128 MB; fan-in 8/32/128 and open-file cost; random, sorted, reversed and
duplicate-heavy patterns and the number of passes; bytes read/written, MB/s
and peak memory; checksum against an in-memory sort on a reduced set.

**Benchmark suite:** external-sort — the same inputs, I/O and time.

**Book:** the external-memory model; why k-way merge rather than two-way; the
cost of an extra pass; why in-memory `sort()` is not a solution.

---

## 9. Phase 9 — Integration scenarios

The topics must not remain isolated exercises. Three scenarios connect them to
the sibling systems:

1. **Cache tier.** Consistent hashing + LRU/LFU: an N-node cluster, node
   addition and failure, hit ratio and key movement. Links to php-mini-cache
   and php-systems-platform.
2. **Log pipeline.** Access-log stream → Bloom for uniqueness → Top-K URLs →
   external sort for a daily report. Links to the observability chapters.
3. **Mini index.** B+Tree + Bloom: point queries and the savings from false
   positives versus a full scan. Links to php-mini-database and
   php-mini-search.

The MVP needs one scenario; v1.0 needs two or three.

**Definition of done:** one command runs the scenario, records its own report
and shows the effect of each structure.

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
