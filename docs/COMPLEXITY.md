# Complexity — Theory, Counters, and Measured Fits

This document is the contract for complexity claims. A chapter may say
"O(log n)" only when the theory, an operation counter, and a measured fit
agree. The tables are filled as chapters land.

## Three kinds of evidence

| Evidence | Question it answers | Where it comes from |
|---|---|---|
| Theory | What should happen as n grows? | The algorithm and its invariants |
| Counters | What did the implementation actually do? | Comparisons, probes, hash calls, node reads, swaps, rehashes |
| Time fit | Does the measurement look like the theory? | Log-log regression over several sizes, with R² |

Time alone is not enough: a PHP-level constant can hide a worse asymptotic
class at the sizes that fit in memory. Counters alone are not enough either:
they say nothing about allocation, cache behavior or the cost of the
operations being counted. The three are reported side by side.

## Counter vocabulary

| Counter | Meaning |
|---|---|
| comparisons | Comparator calls in a sort, heap, tree or search |
| probes | Buckets or slots examined in a hash table |
| hashCalls | Hash function invocations |
| collisions | Keys that landed in the same bucket or slot |
| nodeReads | Tree or page nodes touched |
| swaps | Element moves in an in-place algorithm |
| relinks | Pointer writes in a linked structure or frequency bucket |
| rehashes | Full table rebuilds during resize |

Counters live in src/Instrumentation/Counters.php. A chapter resets them
before an operation, runs the operation, and records the delta. Time benchmarks
leave them off unless the question is specifically about operation counts.

## Fits

For a sequence of sizes n₁…nₖ and measurements t₁…tₖ, the harness fits

```text
log t = log a + b · log n
```

and reports `b`, `a` and R². A slope near 1 suggests linear growth, near 0
constant, near 2 quadratic; `n log n` is tested separately with a model
`t = a + b · n log n` because a single power law cannot distinguish it from
linear at small ranges.

The fit is a description, not a proof. A high R² on three points means the
three points lie on a line; it does not mean the next size will. The chapter
must state the range and the deviations.

## Reading the tables

Each chapter table has:

- the input sizes and the seed;
- the operation being measured and its iteration count;
- median and p95 time per operation;
- peak PHP memory and bytes per element where relevant;
- the counters for the same operation;
- the environment block from `docs/BENCHMARKS.md`.

Rows that differ only by a constant factor are not interesting. Rows that
change the shape of the curve are.

## Chapter 01 — Hash table and Bloom filter

The chapter publishes a counter sweep rather than a log-log time fit. The
benchmark suite has one table size (10,000 keys), so a fit over sizes would be
invented evidence. The load-factor sweep is the controlled variable.

### Separate chaining

Under uniform hashing, a successful search examines about `1 + α/2` entries and
an unsuccessful search about `α` entries, where `α = n / buckets`. The
measured averages (20,000-key run in `docs/BENCHMARKS.md`) were:

| α | successful (theory 1 + α/2) | unsuccessful (theory α) |
|---:|---:|---:|
| 0.50 | 1.315 (1.25) | 0.473 (0.50) |
| 0.75 | 1.485 (1.375) | 0.740 (0.75) |
| 0.90 | 1.634 (1.45) | 0.856 (0.90) |

The direction agrees, and the constants are close. The misses are slightly
cheaper than the formula because this sample's absent keys do not hit every
bucket equally; that is a property of one key set, not a correction to the
model.

### Open addressing

For linear probing, the uniform-hashing approximations are
`0.5 · (1 + 1/(1-α))` for a successful search and
`0.5 · (1 + 1/(1-α)²)` for an unsuccessful one. Measured:

| α | linear hit | linear miss | quadratic hit/miss | double hit/miss |
|---:|---:|---:|---:|---:|
| 0.50 | 1.769 | 2.642 | 1.588 / 2.158 | 1.453 / 1.976 |
| 0.75 | 3.159 | 8.334 | 2.296 / 4.649 | 1.975 / 3.934 |
| 0.90 | 9.928 | 116.095 | 3.620 / 15.848 | 2.722 / 9.992 |

The linear miss at α = 0.90 is 116 probes, more than twice the simple
approximation of 50.5. That is primary clustering: sequential keys and linear
steps create long runs, and a miss walks the run. Quadratic and double probing
stay much lower, but both still grow sharply as the table fills. The complexity
class is still expected O(1) at a fixed α; the table is showing the constant
that the class hides, not a different exponent.

### Bloom filter

The optimal sizing formulas are

```text
m = -n ln p / (ln 2)²
k = (m / n) ln 2
```

For 10,000 items the implementation chose:

| target p | bits m | bits/key | k |
|---:|---:|---:|---:|
| 0.010 | 95,851 | 9.59 | 7 |
| 0.050 | 62,353 | 6.24 | 4 |
| 0.100 | 47,926 | 4.79 | 3 |

Measured false-positive rates were 0.0118, 0.0520 and 0.1059. The estimated
column in `docs/BENCHMARKS.md` uses the actual insertion count; the measured
column is one deterministic sample of absent queries. Neither is a proof of
the formula, and the sample is not a confidence interval.

### Memory

At 20,000 keys the chapter measured 155.9 bytes/key for chaining, 81.0 for open
addressing and 65.5 for a PHP array, using `memory_get_usage(false)`. These are
PHP-allocator numbers on one machine. They explain the representation cost;
they are not portable constants.

## Chapter 02 — LRU / LFU cache

### Expected complexity

| Policy | get | put | remove | Extra memory |
|---|---|---|---|---|
| LRU (map + doubly linked list) | expected O(1) | expected O(1) | expected O(1) | one node per entry |
| LFU (frequency buckets) | expected O(1) | expected O(1) | O(f) when the minimum bucket must be recomputed | one node and one bucket per distinct frequency |
| HeapLFU (lazy heap) | O(log n) | O(log n) | O(1) to mark, stale records cleaned later | one heap record per access |
| ArrayLRU | expected O(1) | expected O(1) | expected O(1) | PHP array entry |
| NaiveLRU | O(n) | O(n) | O(n) | one nested array per entry |

The map lookup is average O(1), not worst-case O(1): PHP's hash table can
degrade. The LFU `remove()` path scans the remaining frequency buckets when
the minimum bucket becomes empty; eviction does the same, so the stated O(1)
applies to the normal get/put path with `minFrequency` maintained, not to an
adversarial sequence of removals.

### Pointer work

`cache:zipf` records `relinks` per request as well as hit rate. At α = 1.0 the
default run (100,000-key space, 20,000 requests) measured:

| capacity | LRU relinks/request | LFU relinks/request |
|---:|---:|---:|
| 100 | 1.9753 | 1.9950 |
| 1,000 | 1.9303 | 1.9500 |
| 10,000 | 1.6074 | 1.6271 |

The count does not grow with capacity. A hit costs a bounded number of pointer
writes (approximately two for moving a node); the variation comes from how many
requests are cold misses and from how often eviction is needed. ArrayLRU reports
zero `relinks` because PHP's array does the ordering internally. This is the
counter evidence for the O(1) pointer claim; the linked-list code cannot hide an
O(n) walk behind the map.

The naive baseline is the contrast: its `get()` increments `comparisons` once
per scanned list element, so a hit near the tail costs O(capacity). In the
benchmark at capacity 10,000 it managed about 15,932 get hits/sec versus
2,728,271 for LRU and 5,525,740 for ArrayLRU. Those are single-size timings, not
a fit, but they show the direction the counter predicts.

### Hit ratio is not complexity

The complexity class says what one operation costs; it does not say whether the
operation hits. Chapter 02 measures three distinct failure modes:

- **Stable Zipf popularity:** LFU beat LRU at small capacities (13.41% versus
  6.63% at α = 0.8, capacity 100) and converged at large capacities.
- **Sequential scan:** LRU's reuse hit rate after a scan was 0.00%, while LFU
  retained 99.90% of the popular set because the scan keys entered at
  frequency one.
- **Popularity shift:** the result reverses. When set B replaced set A and then
  A returned, LRU followed B (90.00% while B was hot) and reloaded A (90.00% on
  return); LFU stayed with A (0.00% while B was hot, 99.99% when A returned).
- **Cyclic scan:** both policies scored 100% for loops that fit in capacity and
  0% for a loop of capacity + 1, despite O(1) operations.

The exact numbers and commands are in [`docs/BENCHMARKS.md`](BENCHMARKS.md).
The loop and shift experiments are the reason the chapter does not name a
universal winner: LFU without decay is not a drop-in improvement over LRU.

### Memory

At 10,000 entries the measured PHP-allocator cost was 192.5 bytes/entry for
LRU, 196.2 for LFU, 325.4 for HeapLFU, 66.6 for ArrayLRU, 403.9 for NaiveLRU
and 65.5 for a plain PHP array. These are engine allocations, not RSS; the
point is the representation overhead, not a portable constant.

### What is not claimed

There is no multi-capacity time fit for Chapter 02. The benchmark suite fixes
capacity and key count, and one point cannot support a slope. The O(1) pointer
claim is backed by the bounded `relinks` counter and the implementation
invariant; the O(n) naive claim is visible in the time comparison and in its
per-scan comparison counter. A future multi-capacity sweep could add a fit, but
it is not presented as if it already exists.

## Chapter 03 — Graph algorithms

### Expected complexity

| Structure or algorithm | Time | Extra memory |
|---|---|---|
| AdjacencyList | add/weight expected O(1); neighbours O(deg); edges O(E) | O(V + E) |
| AdjacencyMatrix | add/has/weight O(1); neighbours/edges O(V) | O(V²) |
| EdgeList | add O(E) to keep the graph simple; neighbours O(E); edges O(E) | O(E) |
| CsrGraph | build O(V + E); neighbours O(deg); edges O(V + E) | O(V + E) |
| BFS / iterative DFS | O(V + E) | O(V) |
| Kahn / DFS topological sort | O(V + E) | O(V + E) including the graph |
| Dijkstra, lazy heap | O((V + E) log V); the heap can hold more than V entries | O(V + E) |
| Dijkstra, indexed decrease-key | O((V + E) log V); the heap holds at most V entries | O(V + E) |
| Bellman-Ford | O(VE) | O(V) |
| Kruskal | O(E log E + E α(V)) | O(V + E) |
| Prim | O(E log V) | O(V + E) |
| UnionFind, compression + rank | O(α(V)) amortized per operation | O(V) |

The graph is simple: an existing pair is replaced, not duplicated. Self-loops
are legal; an undirected self-loop contributes one edge and one arc. An
undirected edge contributes two arcs to traversal, which is why the traversal
counter is `2E`.

The Dijkstra bound uses a binary heap; a Fibonacci heap would change the
decrease-key bound but is not implemented. The EdgeList insertion bound is the
reason it is a baseline, not a default. The adjacency matrix's O(1) edge lookup
is not worth O(V²) memory for a sparse graph.

### Counter evidence

`graph:traversal` uses `Counters::nodeReads` for every neighbour examined. For
an undirected graph with E edges, both BFS and DFS read exactly `2E` arcs:
6,000 for E = 3,000 and 16,000 for E = 8,000. That is direct evidence for
O(V + E) traversal, not an inference from wall-clock time.

`graph:dijkstra` records both successful relaxations and heap pops. On 500
vertices and 2,000 edges:

| algorithm | relaxations | heap pops |
|---|---:|---:|
| lazy binary heap | 893 | 894 |
| decrease-key | 893 | 500 |
| SplPriorityQueue | 888 | 889 |
| Bellman-Ford | 2,068 | 0 |

The lazy heap popped more entries than there are vertices because stale
improvements remain until extracted. The decrease-key heap popped exactly one
entry per reached vertex, which is the structural property its O(V) heap bound
describes. Bellman-Ford's relaxation count is higher because it relaxes every
edge repeatedly; the exact count depends on how quickly a pass stops changing.

`graph:dsu` counts parent reads rather than time. On a 10,000-element chain and
1,000 finds, the naive variant performed 9,999 reads per find; union by rank
alone performed 1; compression alone averaged 21 because the first find paid
for the whole path and the rest were cheap. The counter explains why the
amortized bound is not the same as the worst single find.

### Measured memory and time

At 1,000 vertices and 4,000 edges, `graph:representations` measured 527.3 KiB
for an adjacency list, 16.0 MiB for a matrix, 455.5 KiB for an edge list and
303.1 KiB for CSR. The matrix is the outlier because its V² allocation does not
depend on E. The EdgeList build took 101.9 ms because insertion is O(E); the
CSR build took 1.8 ms because it is a single O(V + E) pass.

The graph benchmark measures BFS/DFS, the three Dijkstra implementations,
Bellman-Ford, both topological sorts, Kruskal and Prim. The full table is in
[`docs/BENCHMARKS.md`](BENCHMARKS.md). Two results are worth keeping in mind:
the C-level SplPriorityQueue was faster than both PHP heaps despite the same
asymptotic class, and Kruskal beat this PHP Prim by about 8.7× on the measured
graph because Prim's candidate heap dominates its runtime. Neither is a
portable algorithm ranking.

### What is not claimed

There is no multi-size time fit for Chapter 03. The experiments fix one V/E
point per question, and the benchmark suite fixes one graph per operation. The
counters and memory tables support the asymptotic claims; a slope estimated
from one size would not. A*, Tarjan SCC, PageRank and a Barabási–Albert
generator are stretch items and were not built, so the chapter makes no claims
about them.

## Chapter 04 — B-tree and B+ tree

### Expected complexity

| Structure or operation | Time | Extra memory |
|---|---|---|
| B-tree search | O(log_t n) node reads, O(t) key work per node | O(n) |
| B-tree insert / delete | O(log_t n) node reads; splits, borrows and merges are O(t) per node | O(n) |
| B+ tree search / insert | O(log_t n) node reads | O(n) |
| B+ range | O(log_t n + k) including k returned keys | O(k) for the result |
| B+ bulk load from sorted keys | O(n) | O(n) |
| SortedArrayIndex search | O(log n) comparisons | O(n) |
| SortedArrayIndex insert / delete | O(n) because elements move | O(n) |
| PhpArrayIndex search / insert / delete | expected O(1) | O(n) |

`t` is the minimum degree: a node has at most `2t - 1` keys and, except for
the root, at least `t - 1`. The B-tree height is therefore O(log_t n), and the
B+ tree uses the same fanout. A B-tree search pays one node read per level;
within a node, a binary search over at most `2t - 1` keys is O(log t), which is
a constant for a fixed t. Deletion is the expensive part to get right, not the
asymptotic part: a child at the minimum is repaired by borrowing from a
sibling or merging before the descent.

The flat sorted array has the same logarithmic search bound but a different
constant: binary search performs O(log n) comparisons and each comparison can
touch a different region. Its insertion and deletion are O(n) because the
array shifts. The PHP array baseline is an unordered hash table: point lookup
is expected O(1), but it cannot answer a range without scanning and sorting.

### Counter evidence

`BTree` and `BPlusTree` accept an optional `Counters` and increment
`nodeReads` on every node visited. `tree:height` uses that counter after
resetting it, so the build work does not leak into the search number. At
50,000 keys:

| t | height | node reads/search |
|---:|---:|---:|
| 4 | 7 | 6.81 |
| 8 | 5 | 4.92 |
| 16 | 4 | 3.96 |
| 32 | 3 | 2.97 |

At one million keys the heights were 9, 6, 5 and 4 for the same t values. The
heights grow much more slowly than n; the node-read average stays close to
the height because an unsuccessful search reads one node per level and a
successful search stops at the leaf. No time fit is claimed from these two
sizes. The measured height and counter are the evidence for the O(log_t n)
claim.

`tree:range` counts internal and leaf node reads. At 100,000 keys and t = 16,
ranges of 10, 100 and 1,000 keys cost 4.29, 7.22 and 36.28 node reads per
query. The first value is essentially the root-to-leaf path; the larger values
show the linked-leaf walk. A range does not repeat the root path for every
leaf.

`tree:page-reads` uses `PageSimulator` to turn each visited node into one or
more page accesses from an estimated node size. At 100,000 keys and t = 16,
the tree height is 4 and the model charged 3.95 pages per search, while binary
search made 16.68 comparisons. These are not the same unit: comparisons are
CPU work, pages are the model's I/O charge. The experiment reports them
separately instead of pretending a comparison is a page.

### Measured memory and time

At 50,000 keys and t = 8, the PHP allocator reported 50.1 bytes/key for the
inserted B-tree, 37.4 for the bulk-loaded B+ tree, 21.2 for a sorted PHP list
and 21.1 for a plain PHP array. The tree pays for explicit node objects and
child arrays; the flat array pays for neither. The B+ tree is smaller than the
B-tree in this run because bulk load packs nodes to capacity, not because a
B+ tree is intrinsically smaller.

The `tree` benchmark fixes one 10,000-key data set and measures point lookups,
batch insertion, bulk load, deletion and range scans. The full table is in
[`docs/BENCHMARKS.md`](BENCHMARKS.md). The PHP array is the fastest point
lookup because it has no ordering to maintain; the B+ range scan is the
operation the flat array cannot provide directly. Deletion is benchmarked as
a batch that builds a 1,000-key tree and deletes 200 keys, so its number
includes construction; it is a demonstration of the rebalancing path, not a
pure delete microbenchmark.

### What is not claimed

The page model is not an operating-system page cache and does not measure
readahead, cache hits or storage latency. The tree is an in-memory PHP data
structure; its node size is estimated, not serialized to disk. B+ deletion was
not implemented, so the chapter does not claim a complete B+ write path.
Bulk-loaded trees may have underfull final nodes; the strict occupancy check is
available for inserted trees, while `validate(false)` accepts the bulk-load
shape. There is no multi-size time fit: the height/counter evidence supports
the asymptotic claim, and the benchmark numbers describe one fixed machine and
one fixed data set.

## Chapter 05 — Consistent hashing

### Expected complexity

Let `N` be the number of physical nodes and `V` the total number of virtual
tokens. A ring has `V = N * virtualNodes` before collisions.

| Structure or operation | Time | Extra memory |
|---|---|---|
| Modulo lookup | O(1) hash plus one modulo | O(1) beyond node list |
| Modulo add/remove | O(N) in this list implementation; the divisor changes | O(N) for nodes |
| HashRing lookup | O(log V) binary search after one hash | O(V + N) |
| HashRing add/remove | O(r) token hashes plus O(V log V) to rebuild the sorted token list, where r is virtual nodes per node | O(V + N) |
| HashRing getNodes(R) | O(log V) to find the start, then walks tokens clockwise and skips duplicate owners; worst-case O(V), usually close to R | O(R) for the result |
| Rendezvous lookup | O(N) hashes, one score per physical node | O(N) for nodes |
| Rendezvous add/remove | O(N) in this list implementation | O(N) for nodes |

The important asymptotic difference is the remapping behavior, not a constant
factor. With a uniformly distributed ring or rendezvous scores, adding one node
to N nodes changes about `1/(N + 1)` of keys; removing one from N + 1 changes
about `1/(N + 1)` as well. Modulo changes almost every key because the divisor
changes: with N = 10, the expected changed fraction for an add is about 10/11 =
90.9%, while the consistent strategies are near 1/11 = 9.1%.

A ring spends memory on virtual tokens to buy balance. More tokens reduce the
variance of the load per node, but every add or remove has more tokens to
rebuild in this implementation. A production ring would keep tokens in a
balanced tree or a sorted array with incremental insertion; the chapter states
the implementation's `O(V log V)` rebuild rather than hiding it behind the
usual O(log V) lookup claim.

### Measured evidence

`ring:remap` mapped 50,000 keys over 10 nodes with 100 virtual nodes per node,
then added an eleventh node and removed `node-0` from the eleven-node ring:

| algorithm | add | remove | ideal |
|---|---:|---:|---:|
| modulo | 91.01% | 90.76% | 9.09% |
| ring | 9.61% | 10.13% | 9.09% |
| rendezvous | 9.03% | 9.09% | 9.09% |

The ring's add fraction is slightly above the ideal because 50,000 keys are a
finite sample and its tokens are not perfectly balanced; the removal value is
slightly above it for the same reason. Rendezvous is close to the ideal because
every node gets a score for every key, so the winner is exactly the node with
the highest score.

`ring:balance` mapped 100,000 keys onto 20 nodes. With one token per node, the
maximum/mean ratio was 1.904 and the coefficient of variation was 0.565. At
100 tokens per node the values were 1.201 and 0.107; at 500 tokens they were
1.068 and 0.042. The chi-square score fell from 31,870.48 to 178.88. It is a
relative error score, not a p-value. The measured improvement is the evidence
for the balance claim; the exact curve depends on the hash and the node ids.

`ring:replicas` uses the same ring for fallback order. With 20,000 keys and 10
nodes, `getNodes(key, R)` returned R distinct owners for R = 1, 2 and 3, and
covered all 10 nodes. Removing `node-2` moved 2,082 keys (10.41%, close to the
10.00% expected share) to their old second replica, while all 17,918 unaffected
keys kept their primary. This validates the ordering rule, not a replication
protocol: no data is copied.

### Time and memory

The `ring` benchmark uses 20 nodes and 100 virtual nodes per node. The ring
lookup took 69.049 ms for 100,000 operations (1,448,252 ops/sec); modulo took
30.104 ms (3,321,840 ops/sec); rendezvous took 254.973 ms for 50,000 operations
(196,098 ops/sec). The ring's binary search over 2,000 tokens is slower than a
single modulo and much faster than hashing all 20 nodes for rendezvous.

Add/remove for 1,000 operations took 295.966 ms for the ring, 0.281 ms for
modulo and 0.281 ms for rendezvous. The ring number is dominated by rebuilding
a sorted list of 2,000 tokens on every change. That is a real cost of this
implementation and the reason `ring:balance` reports the ring size alongside
the balance numbers. The ring is not universally faster; it is the structure
that preserves mappings while remaining a logarithmic lookup.

### What is not claimed

The chapter does not implement weighted nodes, a jump consistent hash, failure
detection, data replication, or a multi-size time fit. The ring positions are
computed with the same xxh128-based function for all three strategies, so the
comparison isolates the mapping strategy. Hash collisions are resolved by
linear probing to the next free position; with the 60-bit space used here they
are rare, so the experiments do not claim a collision distribution. Memory is
reported only as the ring size in tokens, not as bytes; the chapter measures
lookup and remapping behavior, not allocator overhead.

## Chapter 06 — Top-K and stream processing

### Expected complexity

Let `D` be the number of distinct keys seen and `K` the requested result size.

| Structure or operation | Time | Extra memory |
|---|---|---|
| `TopKHeap::observe` | expected O(1) map lookup plus O(log K) heap repair | O(D + K) |
| `TopKHeap::top` | O(K log K) to sort the K entries | O(K) for the result |
| `QuickSelect::top` | average O(D), worst-case O(D²); median-of-three pivot | O(D) for the copied entries and result |
| `CountMinSketch::add` / `estimate` | O(d) hashes and counter reads | O(w × d) counters |
| `SpaceSaving::observe` | O(1) when tracked; O(k) minimum scan when a new key replaces the minimum | O(k) |
| `MisraGries::observe` | O(1) when tracked; O(k) cancellation when full and new | O(k) |
| `SpaceSaving::top` / `MisraGries::top` | O(k log k) | O(k) |
| `StreamGenerator` in these experiments | O(n) to materialize the list | O(n) |

The exact heap does not avoid the count map. A stream with `D` distinct keys
needs a count for every key that can still enter the top K; otherwise a later
occurrence cannot be ranked correctly. That is the chapter's central
asymptotic point: exact top-K is O(D) memory even though the heap is O(K).
Quickselect is an alternative only after that map exists; it trades the
streaming pass for a batch selection and still copies O(D) entries.

Count-Min's standard analysis uses width `w` and depth `d`: the estimate is at
least the true count, and the collision error is roughly `N/w` with a failure
probability controlled by `d`. This implementation fixes `w` and `d` in each
experiment and does not calibrate them to a requested ε/δ pair, so the measured
errors are evidence, not a proof of the bound. The sketch cannot enumerate its
keys; candidate generation is a separate structure. Space-Saving keeps at most
`k` keys and replaces the current minimum; its usual guarantee concerns keys
with frequency above about `N/k`. Misra-Gries keeps at most `k` counters,
decrements every counter when full and a new key arrives, and never
overestimates; the arriving key is not inserted on that cancellation step.

### Measured evidence

`stream:topk` used 100,000 Zipf items, 10,000 possible keys (7,348 observed),
alpha 1.1, and seed `book`:

| K | heap update | heap memory | quickselect | quick memory | exact recall |
|---:|---:|---:|---:|---:|---:|
| 10 | 22.910 ms | 344.0 KiB | 2.781 ms | 4.0 KiB | 100% |
| 100 | 23.259 ms | 338.2 KiB | 2.978 ms | 34.8 KiB | 100% |
| 1,000 | 31.691 ms | 506.0 KiB | 3.441 ms | 347.6 KiB | 100% |

The heap's memory is dominated by the full count map at small K; the K=1,000
row adds about 170 KiB of heap entries. Quickselect does not pay for the map
because the experiment builds it separately; its selection is faster, but it
is a batch operation. The K=10/K=100 heap numbers differ by a few KiB because
the PHP allocator, not because the asymptotic memory changed.

The all-unique experiment makes the lower bound visible. With 100,000 unique
keys and K=100:

| structure | logical size | PHP memory | bytes per unit |
|---|---:|---:|---:|
| exact `TopKHeap` | 100,000 | 5,166.7 KiB | 52.91 B/item |
| `CountMinSketch(2048, 4)` | 8,192 cells | 169.2 KiB | 21.15 B/cell |
| `SpaceSaving(100)` | 100 counters | 18.8 KiB | 192.16 B/slot |
| `MisraGries(100)` | 10 surviving counters | 17.0 KiB | 173.60 B/slot |

The exact map grows with `D`; the sketches stay near their configured size.
The bytes-per-unit columns are not directly interchangeable: a summary entry
owns a key string and a counter, while a Count-Min cell is one integer.

The heavy-hitter experiment used the same Zipf stream and compared detector
recall at capacity K. At K=10/100/1,000, Space-Saving recalled
50.0%/48.0%/55.9% of the true top K and Misra-Gries recalled
30.0%/46.0%/56.4%. The true K=10 threshold was 1,246 occurrences, only 1.25%
of the stream, and the K=1,000 threshold was 8; these are not the safe
above-`N/K` regime. The table is a useful warning, not a claim that a
capacity-K summary always finds the top K.

On the same stream, with an equal counter budget of 4K cells/entries, Count-Min
mean error on the true top-K keys fell from 5,554.60 at K=10 (width 10) to
353.77 at K=100 and 14.52 at K=1,000. Space-Saving's error fell from 252.70 to
3.64 to 0.018; Misra-Gries never overestimated and had errors 1,707.50, 112.18
and 3.98. The counter budget is equal, but the allocator cost is not: the
all-unique table shows why a Count-Min cell and a Space-Saving entry are
different objects.

`stream:drift` switches from a 20-key phase-a hot set to a disjoint 20-key
phase-b set halfway through 100,000 items, with capacity 30. Both summaries
retained ten stale phase-a keys while recalling all 20 current phase-b keys
(100%); Space-Saving's largest counter was 3,820, Misra-Gries's was 1,194.
Capacity alone is not recency. The exact top-K heap would also retain old keys
with high counts if they remained in its heap.

`stream:all-unique` and the child-process test in
`tests/Stream/BoundedMemoryTest.php` are the bounded-memory evidence. The child
runs with `memory_limit=8M` and 150,000 unique keys: the exact `TopKHeap` dies
with an allowed-memory error, while `CountMinSketch(1024, 4)` and
`SpaceSaving(100)` finish. The test lowers the limit only in the child, so the
PHPUnit process and its 512M container limit are not affected.

### Time

The `stream` benchmark uses 50,000 Zipf items over 5,000 keys, with five
repetitions. The median rates were: persistent exact heap updates 3,628,457
items/sec; full 10,000-item heap builds 342 batches/sec (about 3.4M items/sec);
QuickSelect top-1,000 371 selections/sec; Count-Min updates 948,145/sec;
Space-Saving updates 806,782/sec; Misra-Gries updates 8,409,338/sec. The
benchmark prepares its structures before timing, so those numbers are
throughput, not a memory measurement. Misra-Gries is fastest here because its
set can shrink after cancellation and it scans only on a full-set miss; that is
a workload observation, not a universal ordering.

### What is not claimed

The chapter does not build a sliding window or exponential decay, a mergeable
sketch, a parallel stream, or a formal ε/δ calibration. It does not claim that
Count-Min can find top-K keys by itself, that a capacity-K summary always
recalls the true top K, or that equal counter counts mean equal bytes. The
stream generators materialize lists so the same deterministic input can be
replayed; a production pipeline would use a generator and would not keep the
full frequency map next to a bounded summary. No multi-size time fit is
claimed: the benchmark is one fixed shape, and the complexity table is theory
plus targeted counters.

## Status

Phase 1 defines the instrumentation and the fit helper. Chapter 01 publishes
measured counters and memory; its time suite has one size, so it does not claim
a time fit. Chapter 02 adds a counter-backed O(1) pointer claim and measured
hit-ratio behavior, but also has one benchmark size. Chapter 03 adds graph
representation memory, exact traversal counters, heap-pop evidence for
Dijkstra and union-find read counts, still with one size per question. Chapter
04 adds height and node-read evidence at two sizes, a linked-leaf range
counter, and a page model that is kept separate from wall-clock time. Chapter
05 adds remapping fractions against the `1/(n + 1)` ideal, a virtual-node
balance sweep, replica-failure checks, and a fixed-size lookup/add-remove
benchmark. Chapter 06 adds exact-versus-bounded memory evidence, a
child-process memory-limit test, detector recall and estimator error at three
K values, and one fixed-size stream benchmark. Later chapters add multi-size
fits where the question requires one.


Phase 1 defines the instrumentation and the fit helper. Chapter 01 publishes
measured counters and memory; its time suite has one size, so it does not claim
a time fit. Chapter 02 adds a counter-backed O(1) pointer claim and measured
hit-ratio behavior, but also has one benchmark size. Chapter 03 adds graph
representation memory, exact traversal counters, heap-pop evidence for
Dijkstra and union-find read counts, still with one size per question. Chapter
04 adds height and node-read evidence at two sizes, a linked-leaf range
counter, and a page model that is kept separate from wall-clock time. Chapter
05 adds remapping fractions against the `1/(n + 1)` ideal, a virtual-node
balance sweep, replica-failure checks, and a fixed-size lookup/add-remove
benchmark. Later chapters add multi-size fits where the question requires one.
