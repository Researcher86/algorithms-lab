# Decisions — Algorithms Lab

A record of meaningful technical and process decisions, with the reason each
was made. New decisions are appended with a date.

## 2026-10-21 — Docker is the only development environment

- Every command runs through `make` and `docker compose exec php` in the
  project image; host PHP, Composer and extensions are not a supported setup.
- The image is `php:8.5-cli` with `pcntl`, `posix`, Composer, Xdebug and the
  diagnostics needed for benchmarks (`strace`, `time`, `procps`, `htop`).
- CI runs the same make targets in the same image. There is no second
  environment to drift from.
- The project directory is mounted at `/app`; `var/` therefore survives
  container restarts but stays out of git.

## 2026-10-21 — PHP 8.5, matching the ecosystem

- `"php": "^8.5"` matches `php-memory-lab` and `php-mini-cache`.
- The container is the guarantee that the required extensions exist; there are
  no runtime `extension_loaded()` checks in application code.

## 2026-10-21 — Xdebug is installed but off by default

- The image ships Xdebug 3.5.3 with `xdebug.mode=off`. A benchmarking lab
  cannot afford an extension that changes timings just by being loaded.
- Debugging is opt-in: `XDEBUG_MODE=debug XDEBUG_TRIGGER=1` with the
  `-debug` make targets, pointing at the host on port 9003.
- Xdebug is built from the pinned GitHub tag, not installed through PECL:
  `pecl install` returned "No releases available" and the PECL endpoint was
  intermittently a 504. The GitHub tag supports PHP 8.5, and the version is an
  explicit `ARG`, so the build stays reproducible.
- Build dependencies (`$PHPIZE_DEPS`) are installed for the compile layer and
  purged before the image is finalized.

## 2026-10-21 — 512M memory limit in the container

- The default CLI limit of 128M is too small for million-element datasets and
  graph experiments. The project sets `memory_limit=512M`.
- Tests that need to prove bounded memory (external sort, sketches) lower the
  limit explicitly in a child process with `-d memory_limit=128M`; the project
  default does not decide what those tests may use.
- `opcache.enable_cli=0` is explicit rather than inherited: the benchmark
  environment should be the same every run, and a CLI cache that appears later
  should not silently change the numbers.

## 2026-10-21 — One repository for seven chapters

- The book has one `algorithms-lab/README.md`, and the chapters share the
  foundation, instrumentation and benchmark harness.
- Splitting into seven repositories would multiply that shared code and force
  every book link to name a different repository.
- This differs from `php-systems-lab`, where each level is a separate project;
  the unit here is the book's lab, not the system level.

## 2026-10-21 — Namespace and package name

- Composer package: `researcher86/algorithms-lab`.
- PSR-4: `AlgorithmsLab\` → `src/`, `AlgorithmsLab\Tests\` → `tests/`.
- No `App\` prefix: it is accurate for one project, but this repository is
  meant to be read next to the book and the sibling labs, where the package
  name is the identity.

## 2026-10-21 — PHPStan level 8, not max

- The sibling projects use level 8. The dynamic parts of this lab (generic-ish
  containers documented with PHPDoc, dataset arrays, closures in experiment
  descriptors) would need a large baseline at max, which hides real errors.
- Paths grow to `bin/`, `experiments/` and `benchmarks/` in Phase 1.

## 2026-10-21 — No LICENSE file

- As in `php-memory-lab`: `composer.json` declares MIT; the repository does not
  carry a separate `LICENSE` file.

## 2026-10-22 — Descriptors and closures for the harness

- An experiment is a readonly `Experiment` returned by a file under
  `experiments/`; a benchmark is a readonly `Benchmark` returned in a list from
  `benchmarks/`. There is no base class and no central registry.
- Discovery keeps the list honest when files move, and descriptors stay cheap
  because the work lives in a closure that only the runner invokes.
- The alternative — abstract `run()` methods — would make every demo and
  chapter file longer for no shared behavior.

## 2026-10-22 — Unknown experiment options are refused

- `Options` is a closed set shared by every experiment; the runner checks it
  against the experiment's declared `supports` list.
- Silently ignoring `--capacity` in an experiment that never reads it is how a
  result gets published under a configuration it did not run with. The error
  names both the bad option and the options that would work.

## 2026-10-22 — Medians and spread, not a single timing

- A benchmark runs warm-ups, then repeated measurements; the report carries
  min, max, mean, median, p95 and median absolute deviation, and ops/sec is
  derived from the median.
- The median is the headline because one repetition can lose its CPU slice.
  The spread is not optional: with five samples, a p95 is a coarse statement
  and the report says so instead of implying precision.
- Memory is reported as a PHP-allocator delta (`php_delta_bytes`), not RSS.
  Chapters that involve shared or native memory will add the second view
  rather than rename this one.

## 2026-10-22 — The log-log fit is the only complexity fit

- `ComplexityFit::powerLaw()` performs ordinary least squares on logarithms and
  reports slope, intercept, R² and sample count.
- A single power law is easy to explain and easy to falsify. `n log n` will be
  tested with an explicit model in the chapters that need it; the fit is never
  presented as proof of an asymptotic class.

## 2026-10-22 — Chaining and open addressing are both first-class

- The chapter keeps `ChainingHashTable` as the readable baseline and
  `OpenAddressingHashTable` as the memory-conscious alternative. Neither is
  presented as the winner; the experiments report probes, memory and timing.
- Chaining supports a fixed-capacity mode for the distribution experiment.
  Automatic resizing remains the default, and the mode is explicit in the
  constructor rather than hidden in a benchmark.
- Open addressing keeps its capacity a power of two. Quadratic probing uses
  triangular offsets (`i(i+1)/2`), which cover every slot for a power-of-two
  capacity; double hashing uses an odd step for the same reason.
- A tombstone is not an empty slot: unsuccessful searches continue past it, and
  insertions remember the first tombstone but keep probing for an existing key.

## 2026-10-22 — Bloom parameters are reported three ways

- The filter computes `m` and `k` from the expected item count and target rate,
  then reports target, estimated and measured false-positive rates separately.
- The estimated rate comes from the actual fill; the measured rate comes from
  a deterministic set of absent queries. Printing only the target would hide a
  sizing bug, and printing only the measured rate would imply more statistical
  precision than one sample has.
- Double hashing derives all positions from two independent hashes. The filter
  cannot delete or enumerate keys; that limitation is part of the chapter, not
  an implementation detail to paper over.

## 2026-10-22 — PHPStan always starts from a fresh file cache

- PHPStan 2.3's result cache can keep a stale file list when new directories
  appear after an earlier run. `clear-result-cache` did not remove the cached
  file map in this project; `composer analyse` therefore removes
  `/tmp/phpstan` before analysis.
- At this repository's size a fresh analysis takes seconds, and a local green
  run that silently skipped new files is worse than the cost.

## 2026-10-22 — Cache reads update policy; probes do not

- `Cache::get()` is the only operation that updates recency/frequency and the
  hit/miss counters. `Cache::has()` is a non-mutating probe: otherwise a
  monitoring call would change which entry is evicted next.
- `get()` returns `null` both for a miss and for a stored `null`; `has()` tells
  the cases apart. A separate `peek()` or result object would be more explicit,
  but this contract is small and matches the rest of the lab.
- Capacity 0 is legal and stores nothing. Capacity 1 is the smallest useful
  cache and is covered by the shared contract tests. Negative capacity is
  refused at construction.
- `CacheStats` counts only `get()` requests and evictions. Puts and probes do
  not inflate the hit rate; the experiment, not the cache, decides when to
  reset the counters.

## 2026-10-22 — LRU uses sentinels; LFU uses one bucket per frequency

- LRU is a hash map from key to node plus a doubly linked list with head and
  tail sentinels. A hit unlinks the node and links it after the head; the node
  before the tail is the victim. Sentinels remove empty-list branches from the
  hot path.
- LFU keeps one doubly linked list per frequency, `minFrequency`, and an LRU
  order inside each bucket. A hit promotes the node to `frequency + 1` and
  moves it to the front; eviction takes the tail of the minimum bucket. When
  that bucket empties, the minimum is recomputed or advanced.
- This LFU has no frequency decay. The scan experiment shows the win; the
  popularity-shift experiment shows the failure. Segmented LRU and TinyLFU
  would address the trade-off but are deferred rather than hidden in the
  baseline.
- HeapLFU is the comparison point: immutable priority records go into a
  min-heap and stale records are discarded lazily. Its memory grows with the
  number of accesses, which is visible in the measured table.

## 2026-10-22 — Naive and array LRU baselines are first-class

- `NaiveLRU` is an honest O(n) list of pairs: every lookup scans, and a hit uses
  `array_splice()` to move the entry. It is the baseline that makes the
  map-plus-list improvement measurable.
- `ArrayLRU` uses PHP's insertion-ordered array: unset and reinsert on a hit,
  `array_key_first()` for eviction. It is a serious baseline, not a straw man;
  it matches LRU's hit ratio and uses less memory in the measured run.
- The cache benchmark includes a plain PHP array as the language-level floor.
  The point of the chapter is not that a custom cache beats the engine at raw
  lookup; it is what the custom cache buys when eviction and policy are needed.

## 2026-10-22 — Zipf requests are generated, not hand-written

- `ZipfDistribution` builds a cumulative distribution once and samples ranks by
  binary search from `DeterministicRandom`. The cache experiment maps ranks to
  keys and replays the same sequence for every policy and capacity.
- This avoids a hand-written request list that would encode the expected
  popularity ranking and make the result look better than the workload.
- A miss in the experiment is followed by a put, modelling a backing store.
  Hit rate therefore measures the cache policy rather than a read-only replay
  of an already-populated table.

## 2026-10-22 — `relinks` is the cache pointer counter

- `Counters` now has `relinks`: the number of pointer writes performed while
  maintaining a linked list or frequency bucket. A bounded count per request is
  the counter evidence for O(1) policy maintenance; it is not a timing metric.
- The counter is generic because later chapters (LRU in a database buffer pool,
  graph adjacency structures) can reuse it. Chapter-specific meanings are
  documented where the counter is consumed.

## 2026-10-22 — Graph is a simple graph contract first

- `Graph` is read-only for algorithms; `MutableGraph` adds `addEdge()`. Vertex
  ids are integers in `[0, vertexCount)`. Edges are weighted by finite floats,
  self-loops are legal, and adding an existing pair replaces its weight rather
  than creating a parallel edge.
- `edges()` returns one canonical edge for an undirected pair (`from <= to`);
  `neighbors()` returns a map of target to weight and expands undirected edges
  in both directions. Algorithms therefore do not each re-implement direction
  handling.
- A parallel-edge multigraph would need a different edge identity and different
  algorithm results. It is not hidden inside this interface; the simple-graph
  restriction is explicit.
- `EdgeList` keeps the contract by scanning for an existing pair, which makes
  insertion O(E). That cost is deliberate: a compact edge list is a useful
  baseline, and pretending it has O(1) updates would make the representation
  comparison dishonest.
- `CsrGraph` is immutable and built with `fromEdges()`. It canonicalises and
  deduplicates the input edge list, then stores offsets, targets and weights in
  flat arrays. It does not pretend to support incremental inserts.

## 2026-10-22 — Four representations, not one with flags

- `AdjacencyList` is the default: a map per vertex, O(V + E) memory, O(deg)
  neighbour iteration and expected O(1) edge lookup.
- `AdjacencyMatrix` is a flat V×V array filled with `NAN` for absence. A
  separate presence bitmap would make a zero-weight edge easier to explain,
  but `NAN` is one array and the `is_nan()` check is explicit. The matrix is
  dense by construction, which is the point of the memory experiment.
- `EdgeList` is append-and-scan with O(E) neighbour queries and O(E) insertion.
  It is small on disk/in memory and terrible for repeated neighbourhood walks.
- `CsrGraph` is the compact read-only layout: two flat arrays plus offsets,
  built in O(V + E), with O(deg) neighbour iteration. It pays a build pass and
  gives up updates; it is not presented as a universal replacement for a list.

## 2026-10-22 — Traversals are iterative in PHP

- BFS uses a PHP list plus a head index. `array_shift()` would make a large
  queue quadratic; `SplQueue` is a valid alternative but adds object overhead
  and hides the queue mechanics the chapter is explaining.
- DFS uses explicit enter/exit frames. Recursion would be shorter, but a deep
  path can exhaust the PHP stack and the exit marker is what gives postorder
  without a second representation.
- Components are computed only for undirected graphs. A directed graph has weak
  and strong components, and silently choosing one would make a meaningful
  distinction invisible. Tarjan SCC remains a stretch item.

## 2026-10-22 — Two topological sorts and an explicit cycle result

- Kahn's algorithm uses indegrees and a queue; the DFS version uses white/gray/
  black colors and reverse postorder. Both return `null` when a cycle makes a
  total order impossible.
- The experiment validates every edge against the returned order rather than
  comparing the two orders for equality; topological orders are not unique.
- The DFS implementation uses an explicit stack instead of recursion for the
  same reason as traversal: deep DAGs must not depend on the PHP call stack.

## 2026-10-22 — Dijkstra: lazy heap as reference, decrease-key as contrast

- `Dijkstra` uses the Foundation binary heap and lazy deletion: every
  improvement pushes a new entry; stale entries are discarded on pop. It is the
  simplest correct implementation and the baseline for the heap comparison.
- `DijkstraDecreaseKey` uses `IndexedMinHeap`, which stores a vertex-to-index
  map and lowers a priority in place. The heap holds at most V entries. The
  experiment reports relaxations and heap pops so the structural difference is
  visible, not just a timing difference.
- `SplDijkstra` uses `SplPriorityQueue` with negated distances. It is a
  language-level baseline; its internal comparisons and memory are not counted
  by the lab, and it is not treated as proof of a better asymptotic class.
- Dijkstra rejects any negative edge before searching. Bellman-Ford accepts
  negative edges, relaxes all edges for up to V−1 passes, and exposes a
  `negativeCycle` flag instead of throwing away the partial result.
- All three Dijkstra implementations must agree on distances in tests; the
  experiment checks the same invariant before printing.

## 2026-10-22 — Kruskal, Prim and union-find variants

- Kruskal requires an undirected graph, sorts edges by weight (with `from`/`to`
  as deterministic tie-breakers), and returns a forest for a disconnected
  graph. It is the reference because its union-find work is easy to isolate.
- Prim starts at one vertex and returns only that component's tree. It is not
  silently promoted to a forest; the start component is part of the API.
- Both MST algorithms reject directed graphs: a minimum spanning arborescence
  is a different problem and would need a different implementation.
- `UnionFind` now accepts `pathCompression`, `unionByRank` and an optional
  `Counters`. The defaults preserve the original behavior. The experiment
  builds the same chain for every variant and resets the counter after the
  unions, so the reads belong to the measured finds.
- Without union by rank, `union($right, $left)` always attaches the right root
  to the left root. The DSU experiment chooses the union order that makes this
  a deliberately deep chain; a random order would hide the worst case.

## 2026-10-22 — Graph experiments ask one question each

- `graph:representations` holds the edge list fixed and compares memory, build
  time and BFS reach across list/matrix/edge-list/CSR.
- `graph:traversal` compares BFS and iterative DFS on sparse and denser random
  graphs; `nodeReads` should equal `2E` for an undirected graph.
- `graph:dijkstra` compares lazy, decrease-key, SplPriorityQueue and
  Bellman-Ford on one positive weighted graph.
- `graph:topological-sort` validates Kahn and DFS orders on a random DAG and
  rejects a synthetic cycle.
- `graph:dsu` isolates path compression and union by rank on a chain.
- The missing A*, Tarjan SCC, PageRank and Barabási–Albert generator are listed
  as stretch work rather than implied by the chapter. No multi-size time fit is
  claimed; the graph benchmark is a fixed-size suite, and the counters carry
  the asymptotic argument.

## 2026-10-22 — B-tree order is minimum degree, not fanout

- `BTree` calls its parameter `minimumDegree` and follows CLRS: a node has at
  most `2t - 1` keys and, except for the root, at least `t - 1`. Calling it
  "order" without saying maximum children or maximum keys invites an off-by-one
  in the split logic and in the tests.
- Insertion is top-down: a full child is split before the descent, so the
  recursive insert never climbs back up. Deletion is pre-emptive: before
  descending into a child with `t - 1` keys, it borrows from a sibling or
  merges with one. The root is collapsed after deletion if it becomes empty.
- Keys are unique integers. Inserting an existing key returns false and does
  not change the tree. Satellite values were deliberately left out: they would
  make node sizes larger without changing the page-read argument, and the
  chapter is about the index structure.
- `validate()` checks key counts, child counts, sorted keys and equal leaf
  depth. The randomized differential test compares the tree against a sorted
  associative set after hundreds of mixed insert/delete operations, which is
  what catches a broken borrow or merge.

## 2026-10-22 — B+ separators point right on equality

- Internal B+ keys are the first key of the child to their right. A lookup
  therefore uses `upperBound` and takes the right child on equality; the key
  lives in a leaf, not in the internal node.
- Leaves contain every key exactly once and are singly linked in ascending
  order. A range finds the first leaf once and follows `next` until the upper
  bound; it does not return to the root for each leaf.
- Insertion is recursive and returns a promoted separator plus a new right
  sibling when a node overflows. A leaf split keeps the new leaf linked after
  the old one, and an internal split promotes the middle key and divides the
  child pointers. The root split creates a new internal root.
- Bulk load packs leaves to capacity, links them, and builds internal levels
  bottom-up from the first key of each child. The final node at a level may be
  underfull; `validate(false)` accepts that shape while inserted trees use the
  strict occupancy check. A B+ deletion path was not implemented in this
  phase, so the chapter does not imply one.

## 2026-10-22 — PageSimulator is a model, not a page cache

- `PageSimulator` estimates a node's bytes from key width, pointer width and a
  header, charges at least one page per visited node, and exposes the maximum
  keys per page and the resulting bytes/key. `BTree::search()` accepts it as an
  optional observer; the tree itself does not need to know about pages.
- It does not model the operating-system page cache, readahead, dirty pages or
  storage latency. The experiment says so in its own output. The useful claim
  is structural: a B-tree search visits one node per level, while binary search
  makes a logarithmic number of comparisons that may each touch a different
  region.
- The 4 KiB and 8 KiB runs produced the same page count at 100,000 keys because
  a node fit in one page in both models. That is a result about this t and key
  width, not a claim that page size never matters.

## 2026-10-22 — Flat baselines need different build paths

- `SortedArrayIndex::insert()` uses binary search plus `array_splice`, which is
  O(n) and is the honest cost of maintaining a sorted list. Building the
  50,000-key memory baseline through repeated insertion took seconds, so the
  memory and page-read experiments use `fromSortedArray()`, an O(n) copy of an
  already sorted list. The insertion path remains implemented and tested; the
  experiments simply do not pretend that a flat list is a fair insertion
  baseline.
- `PhpArrayIndex` is an unordered hash table. It is the right point-lookup
  baseline and the wrong ordered-range baseline; the chapter keeps both.
- `PageSimulator` reads node sizes, not serialized nodes. `tree:memory` uses
  the PHP allocator delta and labels it as such, not as RSS or disk bytes.

## 2026-10-22 — Tree experiments ask one question each

- `tree:height` changes t while holding a shuffled key set fixed; it reports
  height, bytes/key and search node reads. The one-million-key run is a
  separate command because it is a memory and build-time demonstration, not the
  default smoke test.
- `tree:insert-patterns` compares random, ascending and descending insertion,
  plus B+ bulk load. Height and node reads are the comparison; the build times
  are noisy but show the bulk-load gap.
- `tree:range` bulk-loads a B+ tree and measures three result sizes. It reports
  node reads, not just time, because a linked-leaf walk is the point.
- `tree:page-reads` compares B-tree page-model charges with binary-search
  comparisons at 4 KiB and 8 KiB.
- `tree:memory` compares B-tree, bulk-loaded B+ tree, sorted array and PHP
  array at the same key count.
- No multi-size time fit is claimed. The two height points and the counters
  support the asymptotic argument; the benchmark is a fixed-size suite.
