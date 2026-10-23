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

## 2026-10-23 — One node-hash contract, three strategies

- `NodeHash` exposes only `addNode`, `removeNode`, `getNode` and `nodeCount`.
  `ModuloHash`, `HashRing` and `RendezvousHash` implement it, so the remapping
  experiment changes the strategy without changing the mapping loop. The ring
  adds `getNodes()` for replica order; that operation is intentionally not part
  of the shared contract because modulo hashing has no meaningful clockwise
  fallback.
- All three strategies hash with `Hashers::xxh128()` and take the low 60 bits.
  Using the same function isolates the mapping strategy from hash quality or
  speed. It also keeps every position in a positive signed 64-bit integer.
- Node ids are non-empty strings. Virtual tokens hash `node-id#replica`; this
  makes every token deterministic and human-readable in a debugger.

## 2026-10-23 — Ring positions are sorted on mutation

- `HashRing` stores a position-to-node map, a sorted list of positions, and a
  per-node list of its positions. A lookup hashes the key, binary-searches the
  first position at or after the hash, wraps to zero, and returns that node.
- A token collision probes the next free position modulo the 60-bit space. The
  space is large enough that collisions are rare, but the code does not rely on
  that for correctness.
- `addNode` and `removeNode` rebuild the sorted position list. That is
  `O(V log V)` in the total token count, not the `O(log V)` update a balanced
  tree of tokens would provide. The benchmark shows the cost, and the
  complexity section states it rather than claiming a production implementation.
- `getNodes(key, R)` starts at the same position and walks clockwise, skipping
  virtual tokens owned by a node already in the result. This is the fallback
  order used by the failure experiment. It is not a replication protocol and
  does not move data.

## 2026-10-23 — Virtual nodes are fixed per ring

- `HashRing` takes one `virtualNodes` count for the whole ring. Per-node
  weights would be a useful extension, but they would make the balance
  experiment a different question: the chapter first shows the cost of
  smoothing an unweighted ring.
- 100 virtual nodes is the default. The balance experiment sweeps 1, 10, 100
  and 500 so the reader sees both the distribution improvement and the ring
  growth. The add/remove benchmark uses 20 nodes and 100 tokens per node, which
  gives 2,000 positions.
- The ring does not implement jump consistent hashing. Jump hashing is a
  different algorithm with no ring and no virtual nodes; calling it a ring
  variant would blur the comparison. It remains a possible later chapter
  extension.

## 2026-10-23 — Rendezvous is a real alternative, not a footnote

- `RendezvousHash` scores every physical node for a key and picks the highest
  score. Ties are broken by the smaller node id so the result is deterministic.
  Adding or removing a node only changes keys whose winner was that node, so
  the remapping ideal is the same `1/(n + 1)` as a perfectly balanced ring.
- The price is `O(n)` hashes per lookup. The benchmark shows it: with 20 nodes,
  rendezvous is slower than a binary search over 2,000 ring tokens, while
  add/remove is trivial because there is no ring to rebuild.
- Rendezvous has no `getNodes()` in this phase. Replica selection would mean
  taking the top R scores, which is a natural extension, but the chapter's
  replica experiment is about the ring's clockwise fallback order.

## 2026-10-23 — Remapping is measured against the right ideal

- Adding one node to `n` nodes should move about `1/(n + 1)` of keys. Removing
  one node from `n + 1` should move the same share. The experiment reports both
  the ring and rendezvous fractions against that ideal, and modulo beside them
  as the counterexample.
- Modulo's changed fraction is about `n/(n + 1)`, not `1/(n + 1)`, because the
  divisor changes for almost every key. The table's ideal column is the
  consistent-hashing ideal; the text explains why modulo is expected to miss
  it.
- A finite sample and an imperfect ring produce small deviations (9.61% and
  10.13% around 9.09% in the recorded run). The chapter does not fit a curve to
  two node counts or turn a single run into a universal constant.

## 2026-10-23 — Exact top-K keeps the whole count map

- `TopKHeap` keeps an indexed min-heap of K entries **and** a count for every
  distinct key. The heap bounds the result, not the state needed to maintain an
  exact answer: a key that is currently outside the top K can become a winner
  after later increments, so its count cannot be discarded.
- The heap is indexed by key (`positions`), which makes an existing key's count
  repair O(log K). A lazy heap with duplicate entries would grow with the
  stream and would make the memory claim false.
- Ties are ordered by count descending, then key lexicographically ascending.
  The heap root is the worst entry under that order, so a new equal-count key
  with a larger id does not displace an existing one. Tests compare randomized
  streams with a full sort.
- `TopKHeap::counts()` exposes the full map for the experiments and for
  `QuickSelect`; it is deliberately not a bounded-memory API.
- `QuickSelect` is the exact batch alternative: median-of-three pivot, average
  O(n), worst O(n²), and a full O(n) entry copy. It is not used as a streaming
  algorithm. The benchmark separates its selection time from the cost of
  building the map.

## 2026-10-23 — Count-Min is an estimator, not a detector

- `CountMinSketch` uses `d` rows of `w` counters and hashes `row:key` with the
  shared xxh128 helper. Every update increments one counter per row; an
  estimate is the minimum of the row values. Collisions can overestimate, never
  underestimate.
- The sketch has no key index. It cannot answer "which keys are the top K?" by
  itself. The experiment reports its frequency error on the true top-K keys and
  states that candidate generation is a separate problem. Combining Count-Min
  with a candidate set would be a legitimate extension, but it would change the
  memory comparison and is not hidden behind a misleading "top-K sketch" name.
- The width and depth are fixed by each experiment. There is no automatic
  `w = ceil(e/epsilon)` / `d = ceil(ln(1/delta))` calibration and no formal
  ε/δ claim; the measured error at widths 10/100/1,000 is the evidence.
- The estimator table uses an equal **counter budget** of 4K cells/entries.
  That is not equal allocator memory: a Count-Min cell is an integer, while a
  Space-Saving entry owns a key string and a counter. The all-unique experiment
  reports the actual PHP-memory difference.

## 2026-10-23 — Two different bounded summaries

- `SpaceSaving(k)` keeps at most k key/count entries. A tracked key increments;
  a new key enters while there is room; when full, it replaces the current
  minimum and inherits `minimum + 1`. The estimate is therefore an
  overestimate, and a key evicted early can fail to return even if it is
  genuinely popular. The minimum scan is O(k); this implementation favors
  clarity over a second heap.
- `MisraGries(k)` keeps at most k counters. A tracked key increments; a new key
  enters while there is room; when full, every counter is decremented by one,
  zeros are removed, and the arriving key is **not** inserted on that step.
  Counters are lower bounds on the true frequencies. This is the textbook
  cancellation rule; an earlier draft inserted the arriving key and could
  exceed the capacity, which the tests now reject.
- The two algorithms are not interchangeable. Space-Saving overestimates and
  tends to preserve old heavy keys; Misra-Gries underestimates and can shrink
  its set after cancellation. The heavy-hitter and drift experiments report
  both rather than declaring one universal winner.
- Both `top(k)` methods sort at most k entries, so they are O(k log k). The
  update path is the bounded-memory path that matters.

## 2026-10-23 — Materialized streams and a child-process memory test

- `StreamGenerator` returns lists, not PHP generators. The experiments replay
  the same deterministic input through several algorithms, so materializing it
  once is simpler and keeps the comparison honest; the generator's O(n) memory
  is stated and is not counted as part of a summary's footprint.
- The normal container limit is 512M, which is too large to demonstrate that an
  exact count map is unbounded. `tests/Stream/BoundedMemoryTest.php` writes a
  temporary child script, runs it with `-d memory_limit=8M`, and sends 150,000
  unique keys. The exact `TopKHeap` dies with an allowed-memory error;
  `CountMinSketch(1024, 4)` and `SpaceSaving(100)` complete.
- The child process is deliberate. A PHP fatal memory error is not a catchable
  exception, and lowering the limit in the PHPUnit process would risk killing
  the suite instead of testing the claim.
- The benchmark suite prepares its structures before timing and rotates through
  a fixed stream. Its raw memory columns are benchmark-loop deltas; structure
  memory comes from `stream:all-unique`, where the measurement starts before
  the structure is allocated.

## 2026-10-23 — Drift is not recency

- `stream:drift` switches the hot set halfway through the stream. With capacity
  30 and a 20-key hot set per phase, both summaries recall all 20 current keys
  but retain 10 stale phase-a keys. That is the observed result, not a claim
  that one algorithm forgets.
- Space-Saving retains old keys until they become the minimum; Misra-Gries
  only cancels when its set is full, and a large old counter can survive many
  cancellations. Neither is a sliding window or an exponential-decay summary.
- A recency-sensitive system needs a window, decay, or a time-aware variant.
  Those are explicitly out of scope for Chapter 06, along with sliding-window
  max/min and mergeable summaries.

## 2026-10-23 — External sorting is chunk-sort-spill-merge

- `ExternalSorter` reads an iterable through `ChunkReader`, sorts each chunk,
  writes a sorted run with `tempnam()` and merges at most `fanIn` runs through
  a `BinaryHeap`. It does not attempt a two-way merge chain, and it does not
  materialize the whole input.
- `fanIn` must be at least 2. A one-way "merge" is a copy and would make the
  pass loop unable to reduce the run count. The multi-pass loop groups runs in
  input order, so stability survives group merges.
- Each run is written through a one-megabyte buffer and read with `LineReader`;
  the first implementation wrote one line per `fwrite` call and turned a
  one-megabyte sort into a disk-syscall benchmark. Buffering is an implementation
  detail, not a change to the external-memory model.
- `SortStats` counts the input, every spill write/read and the output as logical
  record bytes. It reports initial runs, merge passes (including the final copy),
  comparisons through the comparator, chunk bytes and fan-in. It does not claim
  those bytes are physical I/O: the OS page cache and Docker filesystem sit in
  between.
- Temporary runs are registered when created and removed in a `finally` block,
  including on exceptions. The user's output file is not part of the temporary
  cleanup. Intermediate runs are unlinked as soon as their group has been
  merged, which keeps peak disk use closer to one pass.
- The final merge always runs, even when the initial input produced one run.
  A rename would be cheaper, but it would make the reported pass and I/O counts
  depend on a special case and would hide the copy the code performs. The stats
  describe what this implementation actually does.

## 2026-10-23 — Stability is chunk order plus run order

- Records are strings; the comparator receives whole records. A caller that
  wants to sort by a field can return a comparator over that field, as the
  dataset experiments do for the leading key.
- PHP's `usort` is stable as of PHP 8.0, so equal records within a chunk retain
  their input order. During merge, the heap orders by the user comparator and
  then by run number; equal records from an earlier run win. The run number is
  the entire stability mechanism, so no per-record sequence is stored or
  serialized.
- The equal-key test forces multiple chunks and multiple merge levels. The
  duplicate-heavy pattern checks all 10,000 output records against a stable
  in-memory `usort`, not a sample.
- A comparator that is inconsistent or non-transitive can still break both
  algorithms; stability is only as well-defined as the comparator. The sorter
  does not validate that contract.

## 2026-10-23 — Chunk size is a memory budget, not a byte count

- `chunkBytes` bounds the sum of record lengths plus newline bytes in a chunk,
  but PHP's array and string overhead is larger. On the 100 MiB input, an 8 MiB
  chunk peaked at 29.6 MiB, 32 MiB at 118.3 MiB and 128 MiB at 291.4 MiB.
- The 128 MiB chunk is therefore not usable under a 128M PHP limit even though
  its nominal input bytes fit. The 500 MiB / 128M run uses a 32 MiB chunk and
  peaks at 118.4 MiB, leaving only about 10 MiB of headroom.
- The experiments report PHP allocator peak, not RSS. A production deployment
  must leave room for the request, autoloader, output buffers and the runtime,
  not just the measured sort.
- A larger chunk reduces initial runs and merge passes, but it does not always
  reduce wall time: the 8 MiB / two-pass run took 5.389 s, the 32 MiB / one-pass
  run 3.954 s and the 128 MiB / one-pass run 3.190 s on the same 100 MiB file.
  The right choice is the largest chunk that fits the limit with margin, not the
  largest nominal size.

## 2026-10-23 — Fan-in trades passes for open files

- With 13 initial runs and fan-in 2, the sorter needs 4 passes and 1,000 MiB of
  logical I/O; fan-in 8 needs 2 passes and 600 MiB; fan-in 32 needs 1 pass and
  400 MiB. Wall time fell from 6.370 s to 5.320 s to 4.985 s.
- Fan-in 32 and 128 both open the 13 initial runs and both make one pass; the
  128-way setting cannot help when there are only 13 runs. Open-file limits and
  memory for run buffers are the real constraints.
- The report prints logical MiB/s, which can rise with extra passes because the
  numerator grows. Wall time and logical I/O must be read together; a larger
  throughput ratio is not automatically a faster sort.
- This implementation uses one heap entry per active run and one `LineReader`
  generator per run. A production implementation might use larger read buffers,
  asynchronous I/O, or a replacement-selection run generator; those are out of
  scope for this chapter.

## 2026-10-23 — Deterministic datasets and a reduced reference

- `DatasetGenerator` writes fixed 76-byte records (10-digit key, space, 64-byte
  payload, newline). `DatasetCache` names a file by pattern, record count and a
  hash of the seed, and regenerates it if the size is wrong. Files live under
  `var/datasets` and are gitignored; experiments replay the same file across
  chunk and fan-in runs.
- The patterns are `random`, `sorted`, `reversed` and `duplicate`. The sorter
  has no pre-sorted detection, so the pattern affects the data distribution but
  not the run/pass path. That is deliberate: the chapter is about external
  sorting, not a run-optimization contest.
- The correctness check uses 10,000 records, not the 100/500 MiB workloads. It
  builds the expected output with stable in-memory `usort`, hashes the complete
  external output with `xxh128` and asserts the counts match. The large runs
  are for performance and memory, not for an in-memory reference.
- The 100/500 MiB targets are generated locally on demand. They are not
  committed, and they are not part of the CI test suite; CI runs the small unit
  tests and the 8 MiB / 8M child test.

## 2026-10-23 — The memory-limit test is a child process

- `tests/ExternalSort/BoundedMemoryTest.php` writes an 8 MiB input, runs a
  child with `-d memory_limit=8M`, sorts with a 512 KiB chunk and fan-in 4, and
  checks the output size. It also runs `file()` + `usort()` on the same input
  in a child and expects a fatal allowed-memory error.
- This makes the bounded-memory claim executable without lowering the PHPUnit
  process limit. A fatal allocator error cannot be caught reliably as an
  exception, and lowering the parent limit would risk killing the suite.
- The exact 8M threshold is not a universal claim about PHP builds or
  extensions. It is a regression guard with a wide margin around the measured
  29.6 MiB peak of an 8 MiB chunk; the test's chunk is much smaller than the
  experiment's.
