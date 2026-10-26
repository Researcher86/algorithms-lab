# Benchmarks: How They Are Run, and How to Read Them

The harness was built in Phase 1. This document is the contract it satisfies;
the measured tables are appended as each chapter lands.

## The rule

A number without its environment is an anecdote. Every report carries:

```text
Benchmark suite: hash-table
PHP 8.5.x on Linux ..., aarch64 x N, memory_limit ..., cgroup ...
OPcache off, JIT off, Xdebug off, allocator Zend MM
git <sha>
```

The fields are recorded because they explain why the same code produces a
different number somewhere else: OPcache and JIT change how the loop itself
runs, the allocator changes what `memory_get_usage()` means, and the cgroup
limit decides how much of the page cache was available.

## The two loops

- The **inner** loop runs the operation `iterations` times and is what gets
  timed. The count belongs to the benchmark: a hash lookup needs many more
  iterations than building a million-element table.
- The **outer** loop repeats the whole measurement `repetitions` times, so the
  report has a distribution rather than one lucky number.

Warm-up repetitions run first and are discarded. `ops/sec` is derived from the
median, not the mean; `min` and `max` show how much the number is worth.

## Counters are a separate instrument

Time says how fast this machine ran. Counters say what the algorithm did:
comparisons, probes, collisions, node reads, rehashes, relinks. Complexity
claims are backed by counters; benchmark tables are backed by time and memory.
Both are reported, never mixed into one number.

## Commands

```bash
make experiment                                  # list every experiment
make experiment ARGS="demo:smoke"
make scenario                                    # integration: Bloom -> top-K -> report

make experiment ARGS="hash:load-factor --elements=20000 --seed=book"
make experiment ARGS="hash:collisions --elements=10000 --capacity=1024 --seed=book"
make experiment ARGS="hash:memory --elements=20000 --seed=book"
make experiment ARGS="bloom:fpr --elements=10000 --seed=book"

make experiment ARGS="cache:zipf --seed=book"
make experiment ARGS="cache:scan --seed=book"
make experiment ARGS="cache:loop --seed=book"
make experiment ARGS="cache:shift --seed=book"
make experiment ARGS="cache:memory --seed=book"

make experiment ARGS="graph:representations --seed=book"
make experiment ARGS="graph:traversal --seed=book"
make experiment ARGS="graph:dijkstra --seed=book"
make experiment ARGS="graph:topological-sort --seed=book"
make experiment ARGS="graph:dsu"

make experiment ARGS="tree:height --seed=book"
make experiment ARGS="tree:insert-patterns --seed=book"
make experiment ARGS="tree:range --seed=book"
make experiment ARGS="tree:page-reads --seed=book"
make experiment ARGS="tree:memory --seed=book"

make experiment ARGS="ring:remap --seed=book"
make experiment ARGS="ring:balance"
make experiment ARGS="ring:replicas"

make experiment ARGS="stream:topk --seed=book"
make experiment ARGS="stream:heavy-hitters --seed=book"
make experiment ARGS="stream:all-unique"
make experiment ARGS="stream:drift --seed=book"

make experiment ARGS="sort:memory-limit --seed=book"
make experiment ARGS="sort:fan-in --seed=book"
make experiment ARGS="sort:patterns --seed=book"
make experiment ARGS="sort:io --seed=book"

make benchmark ARGS="demo"
make benchmark ARGS="hash-table --repetitions=5"
make benchmark ARGS="bloom --repetitions=5"
make benchmark ARGS="cache --repetitions=5"
make benchmark ARGS="graph --repetitions=5"
make benchmark ARGS="tree --repetitions=5"
make benchmark ARGS="ring --repetitions=5"
make benchmark ARGS="stream --repetitions=5"
make benchmark ARGS="external-sort --repetitions=5"
make benchmark ARGS="hash-table --format=json --output=var/results/hash-table.json"
```

Every suite records the environment block. Text is for reading, JSON for
keeping, CSV for comparing runs in a spreadsheet. The `demo` suite is the
smoke test; `hash-table`, `bloom`, `cache`, `graph`, `tree`, `ring`, `stream`
and `external-sort` are the chapter suites.

## Container numbers

The project runs in Docker on macOS during development. Docker Desktop is a
virtualized Linux machine: absolute timings are noisier and not comparable with
a native Linux host. Relative comparisons, operation counters and memory
measurements remain useful. Numbers quoted in the book are taken in one fixed
Linux environment (CI or a dedicated host) and always include the block above.

## Results

### Chapter 01 — Hash table and Bloom filter

These are development numbers, not the book's reference measurements. They
were taken in Docker on macOS at commit `c1276e6`; the harness printed:

```text
PHP 8.5.11 on Linux 7.0.14-linuxkit, aarch64 x 12, memory_limit 512M, cgroup unlimited
OPcache off, JIT off, Xdebug off, allocator Zend MM
```

Absolute times are noisy on a virtualized Linux machine. The operation counts
and the shape of the curves are the durable part of the result.

#### Probes by load factor

`make experiment ARGS="hash:load-factor --elements=20000 --seed=book"`.
The table inserts the same `size` keys into every representation at each
target alpha; `hit` and `miss` are average probes per lookup.

| alpha | items | chaining hit/miss | linear hit/miss | quadratic hit/miss | double hit/miss |
|---:|---:|---:|---:|---:|---:|
| 0.50 | 16,384 | 1.315 / 0.473 | 1.769 / 2.642 | 1.588 / 2.158 | 1.453 / 1.976 |
| 0.75 | 12,288 | 1.485 / 0.740 | 3.159 / 8.334 | 2.296 / 4.649 | 1.975 / 3.934 |
| 0.90 | 14,745 | 1.634 / 0.856 | 9.928 / 116.095 | 3.620 / 15.848 | 2.722 / 9.992 |

Chaining's misses are cheaper than its hits in this sample because missing
keys often land in empty buckets. Open addressing cannot stop at an empty
bucket until it finds one, which is why its miss column explodes as alpha
approaches one. Linear probing suffers most: primary clustering turns a 90%
load factor into roughly 116 probes per miss.

#### Memory

`make experiment ARGS="hash:memory --elements=20000 --seed=book"`:

| representation | PHP memory | bytes/key | capacity |
|---|---:|---:|---:|
| chaining (5,000 buckets) | 3.0 MiB | 155.9 | 5,000 |
| open addressing (32,768 slots) | 1.5 MiB | 81.0 | 32,768 |
| PHP array | 1.3 MiB | 65.5 | 20,000 |

This is `memory_get_usage(false)`, not RSS. Chaining pays for one `Entry`
object per key; open addressing pays for three parallel slot arrays but no
per-key object. A PHP array is already a hash table, and it is the baseline,
not a straw man.

#### Bloom filter

`make experiment ARGS="bloom:fpr --elements=10000 --seed=book"`:

| target p | measured p | estimated p | bits/key | hashes | bit density |
|---:|---:|---:|---:|---:|---:|
| 0.010 | 0.0118 | 0.0100 | 9.59 | 7 | 0.519 |
| 0.050 | 0.0520 | 0.0503 | 6.24 | 4 | 0.477 |
| 0.100 | 0.1059 | 0.1007 | 4.79 | 3 | 0.462 |

The measured rate is one deterministic sample of 10,000 absent queries, not a
confidence interval. It stays near the request without tuning; the estimated
column is the formula's prediction from the actual fill, and the measured
column is what happened.

#### Time

`make benchmark ARGS="hash-table --repetitions=5"` and
`make benchmark ARGS="bloom --repetitions=5"`. Medians, with the environment
above:

| benchmark | iterations | median | ops/sec |
|---|---:|---:|---:|
| chaining-get | 100,000 | 47.486 ms | 2,105,875 |
| open-linear-get | 100,000 | 62.975 ms | 1,587,944 |
| open-quadratic-get | 100,000 | 60.146 ms | 1,662,613 |
| open-double-get | 100,000 | 71.171 ms | 1,405,057 |
| php-array-get | 100,000 | 9.479 ms | 10,549,914 |
| chaining-insert-1k | 10 | 5.313 ms | 1,882 |
| open-linear-insert-1k | 10 | 5.643 ms | 1,772 |
| bloom-add | 100,000 | 150.558 ms | 664,197 |
| bloom-check-hit | 100,000 | 139.027 ms | 719,284 |
| bloom-check-miss | 100,000 | 86.718 ms | 1,153,168 |
| php-array-check | 100,000 | 8.768 ms | 11,405,109 |

For the insert benchmarks, one "operation" is building a 1,000-key table; the
ops/sec column therefore counts table builds, not individual inserts. The
Bloom filter is much slower per query than a PHP array, and that is the point:
it buys a bounded, compact representation, not speed.

#### What the chapter concludes

- Load factor is the variable that matters. At 0.50 all open-addressing
  variants are within a small constant of chaining; at 0.90 the miss cost
  separates them sharply.
- The theory predicts the direction, not the constant. The measured linear
  miss cost at alpha 0.90 is 116 probes, well above the simple uniform-hashing
  approximation, because sequential keys produce clustering.
- Bloom sizing works: the measured false-positive rates are close to the
  requested 1%, 5% and 10%, and the bit density is near 0.5, as the optimal
  formulas predict.
- Hash quality is input-dependent. On this sequential key set CRC32 has the
  lowest chi-square and the smallest maximum bucket; that is not a universal
  ranking, which is why the experiment prints the distribution rather than a
  winner.


### Chapter 02 — LRU / LFU Cache

These are development numbers, not the book's reference measurements. They
were taken in Docker on macOS at commit `f12bb19`; the harness printed:

```text
PHP 8.5.11 on Linux 7.0.14-linuxkit, aarch64 x 12, memory_limit 512M, cgroup unlimited
OPcache off, JIT off, Xdebug off, allocator Zend MM
```

The workloads are deterministic. A `get()` miss is followed by a `put()` to
model a backing store; hit rate counts only `get()` calls. Time and allocator
values still vary between runs on the virtualized Linux host; the tables show
one complete run, not a confidence interval.

#### Zipf hit ratio

`make experiment ARGS="cache:zipf --seed=book"`. The request sequence uses a
100,000-key Zipf space and 20,000 requests per cell. `ArrayLRU` matched LRU in
every cell, so only the LRU and LFU columns are shown.

| alpha | capacity | LRU | LFU |
|---:|---:|---:|---:|
| 0.8 | 100 | 6.63% | 13.41% |
| 0.8 | 1,000 | 19.68% | 24.18% |
| 0.8 | 10,000 | 38.09% | 38.09% |
| 1.0 | 100 | 28.88% | 38.64% |
| 1.0 | 1,000 | 50.08% | 54.13% |
| 1.0 | 10,000 | 62.71% | 62.71% |
| 1.2 | 100 | 61.51% | 68.93% |
| 1.2 | 1,000 | 79.27% | 80.44% |
| 1.2 | 10,000 | 82.77% | 82.77% |

The absolute rates are low because 20,000 requests do not warm a 100,000-key
space and the cold misses are included. The comparison is the point: LFU beats
LRU when popularity is stable, and the gap closes at the largest capacity,
where both policies can hold enough of the working set. At capacity 10,000 the
two policies are identical because the measured sequence does not create a
frequency difference that changes an eviction.

`cache:zipf` also records pointer relinks per request. At alpha 1.0:

| capacity | LRU relinks/request | LFU relinks/request |
|---:|---:|---:|
| 100 | 1.9753 | 1.9950 |
| 1,000 | 1.9303 | 1.9500 |
| 10,000 | 1.6074 | 1.6271 |

The count is bounded by the number of hits and insertions, not by capacity.
That is the counter evidence for constant-time policy maintenance; it is not a
proxy for wall-clock time.

#### Sequential scan

`make experiment ARGS="cache:scan --seed=book"`. Capacity 1,000; ten warm
cycles over the popular set, then 2,000 unique scan keys, then the popular set
again:

| policy | warm hits | scan hits | reuse hits |
|---|---:|---:|---:|
| LRU | 90.00% | 0.00% | 0.00% |
| LFU | 90.00% | 0.00% | 99.90% |
| ArrayLRU | 90.00% | 0.00% | 0.00% |

LRU evicts the entire popular set during the scan; the reuse phase starts cold.
LFU keeps almost all popular keys because the scan keys enter at frequency one.
The missing 0.10% is the price of the first scan insertion, which had to evict
something from the old minimum-frequency bucket.

#### Popularity shift

`make experiment ARGS="cache:shift --seed=book"`. Set A is hot, then set B is
hot, then A is hot again; each set has 1,000 keys and each phase runs ten
cycles:

| policy | A first | B next | A returns |
|---|---:|---:|---:|
| LRU | 100.00% | 90.00% | 90.00% |
| LFU | 100.00% | 0.00% | 99.99% |
| ArrayLRU | 100.00% | 90.00% | 90.00% |

This is the mirror image of the scan result. LFU has no frequency decay, so the
old A keys remain at high frequency; B keeps evicting its own low-frequency
entries and never becomes resident. LRU follows the new set and pays when A
returns. A production cache usually needs decay, aging, or a segmented policy
precisely because both behaviors are undesirable at different times.

#### Cyclic scan

`make experiment ARGS="cache:loop --seed=book"`. Capacity 100; one warm cycle,
then five measured cycles:

| loop length | LRU | LFU | ArrayLRU |
|---:|---:|---:|---:|
| 99 | 100.00% | 100.00% | 100.00% |
| 100 | 100.00% | 100.00% | 100.00% |
| 101 | 0.00% | 0.00% | 0.00% |
| 200 | 0.00% | 0.00% | 0.00% |

A loop of `capacity + 1` is the classic LRU worst case: every request evicts
the key needed by the next request. LFU cannot help because every key has the
same frequency; its LRU tie-break produces the same misses. This is the
simplest demonstration that "O(1) per operation" says nothing about hit rate.

#### Memory

`make experiment ARGS="cache:memory --seed=book"`, 10,000 entries, all at a
capacity equal to the item count:

| representation | PHP memory | bytes/entry |
|---|---:|---:|
| LRU | 1.84 MiB | 192.5 |
| LFU | 1.87 MiB | 196.2 |
| HeapLFU | 3.10 MiB | 325.4 |
| ArrayLRU | 0.64 MiB | 66.6 |
| NaiveLRU | 3.85 MiB | 403.9 |
| PHP array | 0.62 MiB | 65.5 |

This is `memory_get_usage(false)`, not RSS. LRU and LFU each pay for a map plus
one node object per entry. HeapLFU also stores one heap record per access, so
its memory grows with the access count, not just the key count. ArrayLRU is
the cheapest policy here because it borrows PHP's insertion-ordered array;
NaiveLRU pays for a nested PHP array per entry. The ArrayLRU number is close to
the plain PHP array because, at this size, the ordering is the array itself.
A plain PHP array remains the baseline, not a cache with an eviction policy.

#### Time

`make benchmark ARGS="cache --repetitions=5"`. Medians from the same run:

| benchmark | iterations | median | ops/sec |
|---|---:|---:|---:|
| lru-get-hit | 100,000 | 35.781 ms | 2,794,785 |
| lfu-get-hit | 100,000 | 41.256 ms | 2,423,884 |
| heap-lfu-get-hit | 20,000 | 10.457 ms | 1,912,655 |
| array-lru-get-hit | 100,000 | 17.993 ms | 5,557,819 |
| naive-lru-get-hit | 1,000 | 62.439 ms | 16,015 |
| php-array-get | 100,000 | 8.564 ms | 11,676,672 |
| lru-put-evict | 10,000 | 4.848 ms | 2,062,688 |
| lfu-put-evict | 10,000 | 6.225 ms | 1,606,339 |
| heap-lfu-put-evict | 10,000 | 42.572 ms | 234,896 |
| array-lru-put-evict | 10,000 | 3.859 ms | 2,591,512 |
| naive-put-evict | 100 | 3.187 ms | 31,377 |

The linked-list LRU does more PHP object work than ArrayLRU, which is why the
language-level baseline is faster in this microbenchmark. The naive list is
dramatically slower as the capacity grows: its hit lookup scans up to the
current capacity, and its `array_splice()`/`array_shift()` calls move the
remaining elements. HeapLFU is also slower on eviction because each operation
pushes a record and the eviction path pops stale records. The heap get
benchmark used 20,000 iterations instead of 100,000 to keep its lazy-record
memory bounded; its `+12.2 MiB` allocator delta is visible in the JSON report
and is not a steady-state cache cost. The absolute ops/sec values are useful
for comparing policies on one machine; they are not portable constants.

#### What the chapter concludes

- LRU and LFU both achieve bounded pointer work per operation, but their hit
  rates depend on the workload. Stable popularity favors LFU; a scan or a
  shift favors LRU or a decaying policy.
- The loop of `capacity + 1` is a hard failure mode for both policies in this
  workload, even though each operation is constant time.
- ArrayLRU is a serious baseline in PHP: it matches LRU's hit ratio and uses
  less memory, at the cost of relying on the engine's array implementation and
  its own worst-case behavior.
- HeapLFU trades pointer maintenance for heap maintenance and lazy records.
  It is a useful comparison, not a recommendation for a small cache.

### Chapter 03 — Graph Algorithms

These are development numbers, not the book's reference measurements. They
were taken in Docker on macOS at commit `a9e09ef`; the harness printed:

```text
PHP 8.5.11 on Linux 7.0.14-linuxkit, aarch64 x 12, memory_limit 512M, cgroup unlimited
OPcache off, JIT off, Xdebug off, allocator Zend MM
```

The graph is generated once per experiment and then loaded into each
representation or algorithm. Absolute timings are noisy on the virtualized
Linux host; the operation counts and the shape of the results are the durable
part.

#### Representations

`make experiment ARGS="graph:representations --seed=book"`. The same 1,000
vertices and 4,000 undirected weighted edges are loaded into each
representation; BFS starts at vertex 0.

| representation | PHP memory | bytes/vertex | build | BFS seen |
|---|---:|---:|---:|---:|
| AdjacencyList | 527.3 KiB | 540.0 | 1.433 ms | 999 |
| AdjacencyMatrix | 16.0 MiB | 16,795.2 | 2.401 ms | 999 |
| EdgeList | 455.5 KiB | 466.5 | 101.907 ms | 999 |
| CsrGraph | 303.1 KiB | 310.4 | 1.758 ms | 999 |

The random graph contains one isolated vertex, so BFS reaches 999 of 1,000
vertices in every representation. The matrix pays for V² cells even though only
4,000 edges exist. CSR is the smallest because it stores flat target and weight
arrays plus offsets instead of a map per vertex. EdgeList is compact at rest but
its `addEdge()` scans the existing list to keep the graph simple, which makes
the build quadratic at this size. The matrix build is fast because writing V²
cells is cheap compared with the EdgeList scan; memory is the matrix's problem,
not its insert cost.

#### Traversal

`make experiment ARGS="graph:traversal --seed=book"`:

| graph | V | E | BFS | DFS | BFS max distance | components | neighbour reads |
|---|---:|---:|---:|---:|---:|---:|---:|
| sparse | 1,000 | 3,000 | 0.473 ms | 0.932 ms | 7 | 3 | 6,000 |
| denser | 1,000 | 8,000 | 0.457 ms | 1.618 ms | 4 | 1 | 16,000 |

Each undirected edge is read from both endpoints, so both traversals report
exactly `2E` neighbour reads: 6,000 for 3,000 edges and 16,000 for 8,000. That
is the counter evidence for O(V + E), independent of the timing noise. BFS on
the denser graph is slightly faster in this run even though it reads more arcs;
the graph has one component and the queue traversal is cheap compared with the
extra isolated-component work in the sparse case. Do not read a density trend
into one virtualized sample.

#### Dijkstra variants

`make experiment ARGS="graph:dijkstra --seed=book"`, 500 vertices, 2,000
undirected edges, positive weights 1–100, source 0:

| algorithm | time | relaxations | heap pops | node reads |
|---|---:|---:|---:|---:|
| lazy binary heap | 4.289 ms | 893 | 894 | 894 |
| indexed heap, decrease-key | 2.142 ms | 893 | 500 | 500 |
| SplPriorityQueue | 0.720 ms | 888 | 889 | 0 |
| Bellman-Ford | 3.881 ms | 2,068 | 0 | 0 |

The lazy heap popped 894 entries for 500 vertices: the extra entries are stale
improvements that were skipped. The decrease-key heap popped exactly one entry
per reached vertex and held at most V entries. Both produced the same distance
to vertex 499, 140.00. SplPriorityQueue is much faster here because its heap is
implemented in C, not because its asymptotic behavior is better; it is a
language baseline and its internal comparisons are not counted. Bellman-Ford
has no heap but performs repeated full edge passes; on this small positive
graph it is close to the PHP-level Dijkstra, while its O(VE) worst case appears
when V grows and the graph stays dense.

#### Topological sort

`make experiment ARGS="graph:topological-sort --seed=book"`, a random DAG with
1,000 vertices and 4,000 edges:

| algorithm | time | order length | valid |
|---|---:|---:|---:|
| Kahn | 0.832 ms | 1,000 | yes |
| DFS | 0.447 ms | 1,000 | yes |

The experiment checks every edge against the returned order instead of
comparing the two orders for equality; a DAG has many valid topological sorts.
Both algorithms also reject the synthetic cycle `0→1→2→0` and return null.

#### Union-find variants

`make experiment ARGS="graph:dsu"`, 10,000 elements and 1,000 finds after a
deliberately deep chain:

| variant | time | node reads/find | total reads |
|---|---:|---:|---:|
| compression + rank | 0.123 ms | 1.00 | 1,000 |
| compression only | 1.213 ms | 20.996 | 20,996 |
| rank only | 0.103 ms | 1.00 | 1,000 |
| naive | 472.805 ms | 9,999.00 | 9,999,000 |

Compression-only is dominated by the first find, which walks and rewrites the
whole chain; subsequent finds are cheap, so the average is 21 reads per find.
Union by rank alone keeps the chain shallow and needs one read per find. The
naive variant pays the full path on every find. This is the chapter's clearest
example of an amortized result: one expensive operation can be a good deal if
it makes the following operations cheap.

#### Time

`make benchmark ARGS="graph --repetitions=5"`. The median is the time for the
whole inner batch of `iterations` operations, so the per-operation cost is the
median divided by `iterations`.

| benchmark | iterations | median | ops/sec |
|---|---:|---:|---:|
| bfs-sparse | 200 | 44.265 ms | 4,518 |
| dfs-sparse | 200 | 162.659 ms | 1,229 |
| bfs-dense | 100 | 56.474 ms | 1,770 |
| dfs-dense | 100 | 269.785 ms | 370 |
| dijkstra-lazy | 50 | 159.753 ms | 312 |
| dijkstra-decrease-key | 50 | 87.042 ms | 574 |
| dijkstra-spl | 50 | 27.154 ms | 1,841 |
| bellman-ford | 5 | 17.088 ms | 292 |
| topo-kahn | 200 | 125.820 ms | 1,589 |
| topo-dfs | 200 | 93.992 ms | 2,127 |
| kruskal | 50 | 104.786 ms | 477 |
| prim | 50 | 914.798 ms | 54 |

The iterative DFS is slower than BFS in PHP because it stores enter/exit
frames and calls `array_keys()` per visited vertex; that is a constant-factor
cost, not a different asymptotic class. The decrease-key Dijkstra is about
1.8× faster than the lazy heap in this batch, while the C-level
SplPriorityQueue is faster still. Kruskal beats the PHP Prim implementation by
roughly 8.7× on this graph: Kruskal sorts 2,000 edges once and then does nearly
linear union-find work, while Prim pushes and pops candidate edges through a
PHP-level heap. The comparison is implementation-aware, not a claim that
Kruskal is always the better algorithm.

#### What the chapter concludes

- Representation is a memory decision first: an adjacency matrix costs V² even
  when E is small; CSR and a list cost O(V + E).
- BFS and DFS both read each undirected arc from both endpoints. The measured
  `2E` counter is stronger evidence than a single timing.
- Lazy Dijkstra is simpler but can pop more entries than there are vertices;
  decrease-key bounds the heap at V. SplPriorityQueue is a useful C baseline,
  not a replacement for the measured implementation.
- Bellman-Ford is the reference when negative edges are possible, and the
  negative-cycle flag is part of its result rather than an exception.
- Kahn and DFS topological sorts both work; DFS is cheaper here, while Kahn's
  indegree queue is easier to explain and to instrument.
- Path compression and union by rank solve different parts of the union-find
  problem; the naive variant is not merely a little slower on a bad input.
- A*, Tarjan SCC, PageRank and a Barabási–Albert generator were not built in
  this phase. They remain stretch material, not implied results.

### Chapter 04 — B-Tree and Search

These are development numbers, not the book's reference measurements. They
were taken in Docker on macOS at commit `6437acb`; the harness printed:

```text
PHP 8.5.11 on Linux 7.0.14-linuxkit, aarch64 x 12, memory_limit 512M, cgroup unlimited
OPcache off, JIT off, Xdebug off, allocator Zend MM
```

The tree stores integer keys only. That keeps the node-size comparison honest:
there are no satellite values or serialization layers hiding inside the
measurement. `minimumDegree` is the textbook B-tree parameter t: at most
2t - 1 keys per node, at least t - 1 except at the root.

#### Height and fanout

`make experiment ARGS="tree:height --seed=book"` inserts the same shuffled
keys into four B-trees, then searches 1,000 random keys. At 50,000 keys:

| t | height | bytes/key | build | node reads/search |
|---:|---:|---:|---:|---:|
| 4 | 7 | 87.4 | 127.380 ms | 6.81 |
| 8 | 5 | 47.6 | 106.832 ms | 4.92 |
| 16 | 4 | 37.4 | 97.548 ms | 3.96 |
| 32 | 3 | 33.5 | 93.048 ms | 2.97 |

The same experiment at one million keys, with a separate seed, shows the
logarithmic height claim away from a single small sample:

| t | height | bytes/key | build | node reads/search |
|---:|---:|---:|---:|---:|
| 4 | 9 | 86.0 | 4,353.735 ms | 8.76 |
| 8 | 6 | 47.7 | 3,368.201 ms | 5.91 |
| 16 | 5 | 37.6 | 2,999.441 ms | 4.96 |
| 32 | 4 | 33.1 | 2,842.660 ms | 3.98 |

A larger t means fewer, wider nodes and fewer pointer hops. The price is more
key work inside each node and a larger node footprint; bytes/key falls from
87.4 to 33.5 because a wider node amortizes the PHP object and array headers.
Height is not a timing result: the build times are dominated by insertion and
the virtualized host. The node-read counts are the useful comparison.

#### Insertion patterns

`make experiment ARGS="tree:insert-patterns --seed=book"` uses 10,000 keys and
t = 8:

| structure | pattern | height | build | node reads/insert | bytes/key |
|---|---|---:|---:|---:|---:|
| BTree | random | 4 | 17.127 ms | 3.81 | 53.5 |
| BTree | ascending | 5 | 15.922 ms | 4.06 | 53.6 |
| BTree | descending | 5 | 18.374 ms | 4.06 | 48.5 |
| BPlusTree | random | 4 | 26.204 ms | 7.56 | 58.5 |
| BPlusTree | ascending | 4 | 25.670 ms | 7.73 | 56.9 |
| BPlusTree | descending | 5 | 29.780 ms | 8.00 | 50.8 |
| BPlusTree | bulk load | 4 | 0.348 ms | 0.00 | 36.5 |

Sorted insertion is not automatically the best shape. It creates a
right-edge split pattern and can add a level compared with random insertion at
the same n. B+ insertion does a search before the insert, so its node-read
counter is roughly twice the B-tree's; that is the cost of keeping linked
leaves and separators consistent. Bulk load does no search-before-insert and
packs leaves directly, which is why its build is two orders of magnitude
faster at this size. The `0.00` read counter for bulk load means no search
path was used; the bulk builder still touches every key.

#### Range scans

`make experiment ARGS="tree:range --seed=book"` bulk-loads 100,000 keys into
a B+ tree with t = 16 and runs 200 ranges for each size:

| result size | time/query | node reads/query | total results |
|---:|---:|---:|---:|
| 10 | 2.255 µs | 4.29 | 2,000 |
| 100 | 5.926 µs | 7.22 | 20,000 |
| 1,000 | 43.112 µs | 36.28 | 200,000 |

The internal path is paid once per query; the extra cost comes from walking
linked leaves. A range does not return to the root for every leaf. The
100,000-key bulk load took 3.084 ms and 2.84 MB of PHP memory in this run.

#### Page reads versus binary search

`make experiment ARGS="tree:page-reads --seed=book"` uses 100,000 keys, t = 16
and 1,000 searches. The page model assumes one page access per visited B-tree
node; it estimates node size from 8-byte keys, 8-byte pointers and a 24-byte
header.

| page size | height | B-tree pages/query | binary comparisons/query | model bytes/key |
|---:|---:|---:|---:|---:|
| 4,096 B | 4 | 3.95 | 16.68 | 16.03 |
| 8,192 B | 4 | 3.95 | 16.68 | 16.02 |

The B-tree visits about four nodes while binary search makes about seventeen
comparisons. This is a model, not an operating-system page cache: it does not
know about the page cache, readahead, or the cost of a random access. The
honest conclusion is narrower than "B-trees are faster": a tree can turn a
logarithmic number of independent probes into a small number of node-level
accesses, and that matters when a node is the unit of I/O.

#### Bytes per key

`make experiment ARGS="tree:memory --seed=book"` uses 50,000 keys and t = 8:

| structure | height | PHP memory | bytes/key | build |
|---|---:|---:|---:|---:|
| BTree | 5 | 2,446.3 KiB | 50.1 | 68.950 ms |
| BPlusTree (bulk) | 4 | 1,827.9 KiB | 37.4 | 2.541 ms |
| SortedArrayIndex | n/a | 1,037.0 KiB | 21.2 | 1.841 ms |
| PhpArrayIndex | n/a | 1,032.4 KiB | 21.1 | 2.242 ms |

The B+ tree is smaller than the B-tree here because the bulk loader packs
nodes to capacity; the B-tree was built by repeated insertion. The flat
baselines use roughly 21 bytes per key and cannot answer ordered ranges
without scanning or sorting. This is the central trade-off of the chapter:
tree memory buys ordered access and a bounded number of node visits.

#### Time

`make benchmark ARGS="tree --repetitions=5"`. Search benchmarks perform one
lookup per call; insert and bulk-load benchmarks perform a whole batch per
call, as noted by their names.

| benchmark | iterations | median | ops/sec |
|---|---:|---:|---:|
| btree-search | 100,000 | 88.134 ms | 1,134,638 |
| bplus-search | 100,000 | 72.584 ms | 1,377,706 |
| sorted-array-search | 100,000 | 54.620 ms | 1,830,840 |
| php-array-search | 100,000 | 7.976 ms | 12,537,875 |
| btree-insert-1k | 10 | 8.625 ms | 1,159 |
| bplus-insert-1k | 10 | 11.257 ms | 888 |
| bplus-bulk-load-10k | 10 | 3.123 ms | 3,202 |
| btree-build-delete-200 | 10 | 10.448 ms | 957 |
| bplus-range-100 | 100,000 | 476.117 ms | 210,032 |

The PHP array wins the point-lookup race by an order of magnitude because it
is a hash table with no ordering to maintain. The B+ tree is slightly faster
than the B-tree for lookups here; the B-tree's extra space and different node
shape do not make it a universal winner. B+ insertion is slower because it
searches before inserting, while the B-tree uses the same traversal to find
the leaf and split. The range benchmark is a batch of 100,000 ranges; its
ops/sec is ranges per second, not keys per second.

#### What the chapter concludes

- Height is controlled by fanout: at one million keys the height fell from 9
  at t = 4 to 4 at t = 32, while bytes/key fell as headers were amortized.
- Sorted insertion is a different access pattern, not automatically a better
  one. The tree remains valid, but the split pattern changes the height.
- B+ linked leaves make a range scan linear in the result plus one root-to-leaf
  path; the measured node reads show that the path is not paid per leaf.
- A page model supports the I/O argument, but it is not an OS page cache.
  Binary comparisons are not page reads; the model charges a page to each
  tree node and separates the two measurements.
- The flat array is the right point-lookup baseline; the tree is the right
  ordered-index structure. Neither statement needs a universal winner.
- B+ deletion was not implemented in this phase. The B-tree deletion path is
  the rebalancing demonstration; adding B+ deletion would need separate
  borrow/merge rules for internal separators and leaf links.

### Chapter 05 — Consistent Hashing

These are development numbers, not the book's reference measurements. They
were taken in Docker on macOS at commit `9ebaa1e`; the harness printed:

```text
PHP 8.5.11 on Linux 7.0.14-linuxkit, aarch64 x 12, memory_limit 512M, cgroup unlimited
OPcache off, JIT off, Xdebug off, allocator Zend MM
```

All three strategies hash with the same xxh128-based function, so the
comparison is about the mapping strategy rather than a faster hash. Node ids
are strings; a virtual token is hashed from `node-id#replica`.

#### Remapping after a node change

`make experiment ARGS="ring:remap --seed=book"` uses 50,000 keys, 10 nodes and
100 virtual nodes per node. It maps every key, adds an eleventh node, maps
again, then removes `node-0` from the eleven-node ring and measures again.

| algorithm | change | changed fraction | ideal |
|---|---|---:|---:|
| modulo | add | 91.01% | 9.09% |
| modulo | remove | 90.76% | 9.09% |
| ring | add | 9.61% | 9.09% |
| ring | remove | 10.13% | 9.09% |
| rendezvous | add | 9.03% | 9.09% |
| rendezvous | remove | 9.09% | 9.09% |

Modulo hashes the key and takes it modulo the current node count. Changing the
divisor changes almost every remainder, so the cache is effectively flushed.
The ring and rendezvous change only the keys that were owned by the changed
node. Their fractions are close to the ideal `1/(n + 1)`, not exactly equal:
this is a finite sample and the ring's tokens are not perfectly balanced. The
ideal is the share of keys the changed node owned, not a guarantee for every
run.

#### Virtual-node balance

`make experiment ARGS="ring:balance"` maps 100,000 keys onto 20 nodes. With one
token per node the ring is visibly lumpy; more tokens smooth it.

| virtual nodes | ring size | max/mean | coefficient of variation | chi-square score |
|---:|---:|---:|---:|---:|
| 1 | 20 | 1.904 | 0.565 | 31,870.48 |
| 10 | 200 | 1.485 | 0.276 | 7,628.24 |
| 100 | 2,000 | 1.201 | 0.107 | 1,150.45 |
| 500 | 10,000 | 1.068 | 0.042 | 178.88 |

The chi-square column is a relative error score: `sum((observed - expected)^2 /
expected)`, not a p-value. Lower is better. The maximum-to-mean ratio improves
from 1.904 to 1.068, and the coefficient of variation falls from 0.565 to
0.042. The price is visible in the ring size: 10,000 tokens at 500 virtual
nodes. Lookup remains a binary search, but adding or removing a node has more
tokens to rebuild.

#### Replicas and failure

`make experiment ARGS="ring:replicas"` uses 20,000 keys, 10 nodes and 100
virtual nodes per node. `getNodes(key, R)` walks clockwise and skips duplicate
owners.

| R | average distinct owners | node coverage |
|---:|---:|---:|
| 1 | 1.00 | 10/10 |
| 2 | 2.00 | 10/10 |
| 3 | 3.00 | 10/10 |

The experiment then removes `node-2` and checks the fallback claim:

- 2,082 keys (10.41%) had `node-2` as primary; the expected share was 10.00%.
- All 2,082 of those keys moved to the old second replica.
- All 17,918 unaffected keys kept their primary.

This is a fallback order, not a replication protocol. The experiment does not
copy data, detect failures, or promise that the second replica is on a
different physical host; it only demonstrates the ring's ordering rule.

#### Time

`make benchmark ARGS="ring --repetitions=5"` uses 20 nodes and 100 virtual
nodes per node. Add/remove benchmarks perform one add and one remove per
operation, so their ops/sec is add-remove pairs per second.

| benchmark | iterations | median | ops/sec |
|---|---:|---:|---:|
| ring-lookup | 100,000 | 69.049 ms | 1,448,252 |
| ring-replicas-3 | 50,000 | 50.540 ms | 989,312 |
| modulo-lookup | 100,000 | 30.104 ms | 3,321,840 |
| rendezvous-lookup | 50,000 | 254.973 ms | 196,098 |
| ring-add-remove | 1,000 | 295.966 ms | 3,378 |
| modulo-add-remove | 1,000 | 0.281 ms | 3,556,605 |
| rendezvous-add-remove | 1,000 | 0.281 ms | 3,553,445 |

Modulo is the fastest lookup and the worst at preserving mappings. Rendezvous
has no ring to rebuild and moves the ideal share, but every lookup hashes every
node; with 20 nodes it is about four times slower than the ring in this run.
The ring's lookup is a binary search over 2,000 tokens. Add/remove is much
slower in this implementation because each change rebuilds the sorted token
list; that is a deliberate, measured trade-off, not an asymptotic requirement
of consistent hashing (a balanced tree of tokens would change the constant).

#### What the chapter concludes

- Modulo is the right baseline precisely because it is fast and simple; it is
  the wrong choice when the node set changes, because remapping is the cache
  flush.
- A ring moves about `1/(n + 1)` of keys when one node changes. The measured
  9.61%/10.13% at ten and eleven nodes is close to the 9.09% ideal.
- Virtual nodes buy balance. At 20 nodes, 100 tokens per node reduce the
  maximum/mean ratio to 1.201 and the coefficient of variation to 0.107; 500
  tokens improve it further but cost five times the ring memory.
- Rendezvous hashing has the same remapping property without virtual nodes or
  a ring, at O(n) hash cost per lookup. It is a legitimate alternative, not a
  footnote.
- Replicas are the next `R` distinct clockwise owners. Node failure moves the
  affected keys to the next owner, and keys on other nodes stay put.

### Chapter 06 — Top-K and Stream Processing

These are development numbers, not the book's reference measurements. They
were taken in Docker on macOS at commit `cb5d38e`; the harness printed:

```text
PHP 8.5.11 on Linux 7.0.14-linuxkit, aarch64 x 12, memory_limit 512M, cgroup unlimited
OPcache off, JIT off, Xdebug off, allocator Zend MM
```

The Zipf experiments use 100,000 stream items over a 10,000-key alphabet with
alpha 1.1 and seed `book`; the realized stream contained 7,348 distinct keys.
The all-unique experiment uses a different stream and is marked as such.

#### Exact top-K: heap versus quickselect

`make experiment ARGS="stream:topk --seed=book"` feeds the same stream to an
exact `TopKHeap` and then runs `QuickSelect` over the already-built full
frequency map.

| K | distinct | heap update | heap memory | quickselect | quick memory | recall |
|---:|---:|---:|---:|---:|---:|---:|
| 10 | 7,348 | 22.910 ms | 344.0 KiB | 2.781 ms | 4.0 KiB | 100.0% |
| 100 | 7,348 | 23.259 ms | 338.2 KiB | 2.978 ms | 34.8 KiB | 100.0% |
| 1,000 | 7,348 | 31.691 ms | 506.0 KiB | 3.441 ms | 347.6 KiB | 100.0% |

Both methods are exact, so the recall column is expected to be 1.0. The heap
memory is the important column: it includes the count for every distinct key,
not just the K entries in the heap. The K=10 and K=100 readings are within
allocator noise of each other because the full map dominates; K=1,000 adds
roughly 170 KiB of heap entries. Quickselect is faster here because it is a
single batch selection over a map that already exists; it does not include the
cost or memory of building that map. The heap is the streaming option; the
selection is the batch option.

#### Heavy hitters: detection and estimation

`make experiment ARGS="stream:heavy-hitters --seed=book"` compares two
detectors at capacity K and then compares three estimators on the true top-K
keys. A detector must decide which keys to keep; an estimator only scores keys
it is given.

Detectors at capacity K:

| K | summary | entries | recall | precision | mean rank error |
|---:|---|---:|---:|---:|---:|
| 10 | space-saving | 10 | 50.0% | 50.0% | 2.40 |
| 10 | misra-gries | 8 | 30.0% | 37.5% | 3.10 |
| 100 | space-saving | 100 | 48.0% | 48.0% | 21.73 |
| 100 | misra-gries | 87 | 46.0% | 52.9% | 22.79 |
| 1,000 | space-saving | 1,000 | 55.9% | 55.9% | 198.01 |
| 1,000 | misra-gries | 989 | 56.4% | 57.0% | 200.77 |

The true top-K threshold is 1,246 occurrences at K=10, 96 at K=100 and 8 at
K=1,000. A frequency-summary guarantee is usually stated for keys above about
N/K; at K=10 the threshold is only 1.25% of N, and the measured recall is
50%. The table is not a contradiction: a summary of capacity K cannot preserve
every candidate when the distribution has a long tail and many near-ties.
Space-Saving keeps K counters and overestimates survivors; Misra-Gries can use
fewer counters and underestimates them, which is why its precision can exceed
recall.

Frequency estimators on the true top-K keys, with an equal counter budget of
4K cells or entries:

| K | estimator | units | mean error | max overestimate |
|---:|---|---:|---:|---:|
| 10 | count-min (w=10, d=4) | 40 cells | 5,554.60 | 7,574 |
| 10 | space-saving | 40 entries | 252.70 | 683 |
| 10 | misra-gries | 15 entries | 1,707.50 | 0 |
| 100 | count-min (w=100, d=4) | 400 cells | 353.77 | 920 |
| 100 | space-saving | 400 entries | 3.64 | 23 |
| 100 | misra-gries | 291 entries | 112.18 | 0 |
| 1,000 | count-min (w=1,000, d=4) | 4,000 cells | 14.52 | 72 |
| 1,000 | space-saving | 4,000 entries | 0.018 | 2 |
| 1,000 | misra-gries | 2,906 entries | 3.98 | 0 |

"Equal counter budget" is not equal allocator bytes: a Count-Min cell is an
integer in a row, while a summary entry owns a key string and a counter. The
all-unique table below shows that difference. Count-Min's mean error falls as
its width grows (5,554.60 at w=10, 353.77 at w=100, 14.52 at w=1,000), which is
the collision behavior the sketch trades memory for. Space-Saving is a much
better estimator on this skewed stream at the same counter count; Misra-Gries
is conservative and never overestimates. Count-Min cannot enumerate its own
keys, so the estimator table deliberately gives it the true keys. It is not a
detector and should not be read as one.

#### All-unique stream: the memory lesson

`make experiment ARGS="stream:all-unique"` sends 100,000 distinct keys, each
once, through four structures with K=100 and Count-Min width 2,048/depth 4.

| structure | logical size | PHP memory | bytes per unit | sample |
|---|---:|---:|---:|---:|
| TopKHeap (exact) | 100,000 | 5,166.7 KiB | 52.91 B/item | 1 |
| CountMinSketch | 8,192 | 169.2 KiB | 21.15 B/cell | 36 |
| SpaceSaving | 100 | 18.8 KiB | 192.16 B/slot | 1,000 |
| MisraGries | 10 | 17.0 KiB | 173.60 B/slot | 1 |

The exact heap grows with the number of distinct keys even though K is only
100; that is why "top-K needs O(K) memory" is false for an arbitrary stream.
The bounded summaries stay near their configured size. The `sample` column is
not a ranking: it is the exact top count, the sketch's estimate for the first
item, or the summary's largest counter. Space-Saving's 1,000 is an inherited
overestimate; Misra-Gries's 1 is an underestimate. With all frequencies equal,
there is no winner to find.

`tests/Stream/BoundedMemoryTest.php` makes the point executable. It starts a
child process with `-d memory_limit=8M` and sends 150,000 unique keys:

- the exact `TopKHeap` dies with `Allowed memory size ... exhausted`;
- `CountMinSketch(1024, 4)` completes;
- `SpaceSaving(100)` completes.

The child-process boundary is deliberate: a fatal memory error cannot be
caught reliably in the PHPUnit process, and the container's normal 512M limit is
too generous to test a bounded-memory claim.

#### Concept drift

`make experiment ARGS="stream:drift --seed=book"` sends 100,000 items from a
200-key alphabet. The first half repeats a 20-key hot set from `phase-a`; the
second half repeats a disjoint 20-key hot set from `phase-b`. Both summaries
have capacity 30.

| summary | entries | current-hot recall | stale phase-a keys in top | top count |
|---|---:|---:|---:|---:|
| space-saving | 30 | 100.0% | 10 | 3,820 |
| misra-gries | 30 | 100.0% | 10 | 1,194 |

The current-hot recall is 100% because all 20 phase-b keys fit in the 30 slots.
The interesting number is the ten stale phase-a keys: both summaries retain
old heavy keys, so a bounded summary is not a sliding window. Space-Saving
replaces the current minimum; Misra-Gries decrements every counter when its
set is full, but a large old counter can survive. A recency-sensitive pipeline
needs a window or a decay factor, which this chapter does not build.

#### Time

`make benchmark ARGS="stream --repetitions=5"` uses a 50,000-item Zipf stream
over 5,000 keys. The structures are prepared before each benchmark, so the
memory columns in the raw report are benchmark-loop deltas, not the size of
the data structures; the experiments above are the memory source.

| benchmark | iterations | median | ops/sec |
|---|---:|---:|---:|
| stream-topk-observe | 100,000 | 27.560 ms | 3,628,457 |
| stream-topk-batch | 20 | 58.371 ms | 342 |
| stream-quickselect-1000 | 100 | 268.911 ms | 371 |
| stream-count-min | 100,000 | 105.469 ms | 948,145 |
| stream-space-saving | 100,000 | 123.949 ms | 806,782 |
| stream-misra-gries | 100,000 | 11.892 ms | 8,409,338 |

`stream-topk-batch` runs a complete 10,000-item heap build per operation, so
342 operations/sec is about 3.4 million items/sec. `stream-topk-observe`
updates a persistent heap and rotates through the stream; its 3.6 million
items/sec is a per-item update, not a full pass. Quickselect performs 1,000-way
selection over the 5,000-key map.

Count-Min performs four hashes per update. Space-Saving scans the current
minimum when a new key arrives after capacity is full. Misra-Gries only scans
when its set is full and the arriving key is new; the measured set can shrink
after cancellation, which is why its throughput is higher. That is a property
of this workload, not a universal ranking. A production stream would not
materialize the stream or the full frequency map; these experiments do, because
they replay one deterministic dataset through several algorithms.

#### What the chapter concludes

- Exact top-K is a memory question before it is a heap question: the count map
  is O(distinct), and 100,000 unique items cost 5,166.7 KiB despite K=100.
- The bounded summaries cost tens of KiB and keep working when the exact map
  would exhaust a lowered memory limit.
- Count-Min is an estimator, not a candidate finder. Space-Saving and
  Misra-Gries produce candidate sets; their recall is workload-dependent and
  not guaranteed for keys below the N/K threshold.
- Equal counter counts are not equal bytes. The estimator comparison must be
  read with the all-unique memory table, not as a claim that 40 counters and
  40 cells have identical allocator cost.
- Concept drift exposes the difference between bounded and recent: capacity
  alone does not forget old heavy keys.
- No multi-size time fit is claimed. The tables describe one Docker run and one
  fixed stream shape.

### Chapter 07 — External Sorting

These are development numbers, not the book's reference measurements. They
were taken in Docker on macOS at commit `e2e5536`; the harness printed:

```text
PHP 8.5.11 on Linux 7.0.14-linuxkit, aarch64 x 12, memory_limit 512M, cgroup unlimited
OPcache off, JIT off, Xdebug off, allocator Zend MM
```

The generated inputs use 76-byte records: a 10-digit key, a space, a 64-byte
payload and a newline. A 100 MiB target is 1,379,705 records (104,857,580
bytes); a 500 MiB target is 6,898,526 records (524,287,976 bytes). Dataset
generation is excluded from every time below; files are cached under
`var/datasets/` and are not committed. All experiments use seed `book`.

#### Chunk size

`make experiment ARGS="sort:memory-limit --seed=book"` sorts the same 100 MiB
file with 8, 32 and 128 MiB chunks and a fixed fan-in of 8.

| chunk | runs | passes | elapsed | logical I/O | MiB/s | PHP peak |
|---:|---:|---:|---:|---:|---:|---:|
| 8 MiB | 13 | 2 | 5.389 s | 600.0 MiB | 111.33 | 29.6 MiB |
| 32 MiB | 4 | 1 | 3.954 s | 400.0 MiB | 101.15 | 118.3 MiB |
| 128 MiB | 1 | 1 | 3.190 s | 400.0 MiB | 125.38 | 291.4 MiB |

The 8 MiB run needs two merge passes and therefore rewrites the data once more:
600 MiB of logical I/O versus 400 MiB for one pass. The 128 MiB chunk is fastest
on this 512M container, but its PHP footprint is 291.4 MiB, not 128 MiB, because
the chunk is an array of strings and the input buffer. It cannot be used under a
128M limit. The 32 MiB chunk peaked at 118.3 MiB and is the largest chunk that
fits the 500 MiB / 128M run below with only a narrow margin.

Passes include the final merge. Even a single run is copied through that final
merge in this implementation, so the one-run row still reports one pass and
400 MiB of logical I/O (input, spill write, spill read, output).

#### Fan-in

`make experiment ARGS="sort:fan-in --seed=book"` uses the 100 MiB file, an
8 MiB chunk and 13 initial runs.

| fan-in | runs | passes | open files | elapsed | logical I/O | MiB/s | PHP peak |
|---:|---:|---:|---:|---:|---:|---:|---:|
| 2 | 13 | 4 | 2 | 6.370 s | 1,000.0 MiB | 156.98 | 29.6 MiB |
| 8 | 13 | 2 | 8 | 5.320 s | 600.0 MiB | 112.79 | 29.6 MiB |
| 32 | 13 | 1 | 13 | 4.985 s | 400.0 MiB | 80.23 | 29.6 MiB |
| 128 | 13 | 1 | 13 | 4.647 s | 400.0 MiB | 86.07 | 29.6 MiB |

The `MiB/s` column is logical bytes divided by wall time. It is higher for
fan-in 2 because three extra passes inflate the numerator; wall time is worse.
Fan-in 32 and 128 both open the 13 initial runs, so the extra fan-in cannot
remove another pass. A real service has to choose fan-in against its open-file
limit, not only against the merge count.

#### Input patterns

`make experiment ARGS="sort:patterns --seed=book"` uses 10,000 records per
pattern, a 16 KiB chunk and fan-in 4. The comparator sees only the 10-digit
key; the payload stays in the record. Every complete output was checked against
a stable in-memory `usort` on the same reduced set.

| pattern | records | runs | passes | external | in-memory | checksum | PHP peak |
|---|---:|---:|---:|---:|---:|---:|---:|
| random | 10,000 | 47 | 3 | 0.091 s | 0.012 s | ok | 0.8 MiB |
| sorted | 10,000 | 47 | 3 | 0.079 s | 0.009 s | ok | 0.8 MiB |
| reversed | 10,000 | 47 | 3 | 0.080 s | 0.013 s | ok | 0.8 MiB |
| duplicate | 10,000 | 47 | 3 | 0.096 s | 0.011 s | ok | 0.8 MiB |

The sorter does not detect a pre-sorted or reversed input; all four patterns
take the same runs and passes. The duplicate pattern is the stability test:
many records share a key, and chunks plus run-number tie-breaking preserve the
input order. The in-memory sort is much faster at this tiny size; that is the
point of the comparison, not a claim that external sorting is faster.

#### I/O and the 128M run

`make experiment ARGS="sort:io --seed=book"` uses the 100 MiB file, an 8 MiB
chunk and fan-in 32. The 500 MiB measurements use
`--input-mb=500 --chunk-mb=32 --fan-in=32`; the 128M row was run directly as:

```bash
docker compose exec -T php php -d memory_limit=128M bin/experiment sort:io \
  --input-mb=500 --chunk-mb=32 --fan-in=32 --seed=book
```

| input | limit | runs | passes | elapsed | read | written | logical I/O | MiB/s | PHP peak |
|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| 100 MiB | 512M | 13 | 1 | 4.659 s | 200.0 MiB | 200.0 MiB | 400.0 MiB | 85.86 | 29.6 MiB |
| 500 MiB | 512M | 16 | 1 | 27.201 s | 1,000.0 MiB | 1,000.0 MiB | 2,000.0 MiB | 73.53 | 118.4 MiB |
| 500 MiB | 128M | 16 | 1 | 26.843 s | 1,000.0 MiB | 1,000.0 MiB | 2,000.0 MiB | 74.51 | 118.4 MiB |

“Read” includes the input and every spilled run read; “written” includes every
spill and the output. The 500 MiB / 128M run fits, but the 118.4 MiB peak leaves
only about 10 MiB of headroom under the PHP limit. The operating system page
cache can satisfy some reads from memory; the peak is the PHP allocator, not
RSS.

#### Time

`make benchmark ARGS="external-sort --repetitions=5"` uses an 8 MiB input and
one sort per timed iteration. Five repetitions and one warm-up are measured.

| benchmark | median | p95 |
|---|---:|---:|
| external-sort-chunk-1m | 435.965 ms | 531.748 ms |
| external-sort-chunk-4m | 221.379 ms | 222.934 ms |
| external-sort-fanin-2 | 421.688 ms | 432.094 ms |
| external-sort-fanin-16 | 336.893 ms | 339.214 ms |

The chunk-1m and fan-in rows use a 1 MiB chunk; the chunk-4m row uses 4 MiB.
Fan-in is 4 unless the benchmark name says otherwise. The 4 MiB chunk is fastest
here because the 8 MiB input becomes only two initial runs and one merge pass. The benchmark's
raw PHP-memory columns are loop deltas, not the size of the sorter's structures;
the experiments above are the memory source.

#### Bounded-memory test

`tests/ExternalSort/BoundedMemoryTest.php` generates an 8 MiB input, starts a
child process with `-d memory_limit=8M`, and sorts with a 512 KiB chunk and
fan-in 4. The external sort completes; a naive `file()` + `usort()` on the same
file dies with an allowed-memory error. The child boundary is deliberate: a
fatal allocator error is not safely catchable in PHPUnit.

#### What the chapter concludes

- External sorting bounds memory with the chunk, not with the input; the rest
  of the data is spilled and merged.
- A larger chunk reduces runs and passes but raises the live PHP footprint. The
  raw chunk size is not the PHP memory cost: 128 MiB of records measured
  291.4 MiB.
- Fan-in reduces passes up to the number of runs; beyond that it adds open files
  without helping. Extra passes multiply I/O and usually lose wall time even
  when a logical-throughput ratio looks larger.
- Pre-sorted, reversed and duplicate-heavy inputs do not get a free shortcut in
  this implementation; stability is explicit and tested.
- A 500 MiB input can be sorted under 128M with a 32 MiB chunk. A 128 MiB chunk
  needs the larger container limit.
- No multi-size time fit is claimed. This is one file shape, one Docker host and
  one cache state.

### Integration scenario — log pipeline

`make scenario` is one run, not a benchmark suite: it demonstrates the composed
Bloom → exact top-K → external-report path and writes a durable report. The
numbers below are from the default 100,000-request / 20,000-rank stream at code
commit `4fe7adb`, in the same PHP 8.5.11 Docker environment (`Linux
7.0.14-linuxkit, aarch64 x 12, memory_limit 512M`, OPcache/JIT/Xdebug off).
The JSON form is:

```bash
make scenario ARGS="--format=json --output=var/results/log-pipeline.json"
```

The stream is deterministic Zipf with alpha 1.1 and seed `book`. The default
report sort uses a 32 KiB chunk and fan-in 8.

| Measurement | Value |
|---|---:|
| Requests | 100,000 |
| URL ranks in the alphabet | 20,000 |
| Exact distinct URLs | 10,442 |
| Bloom bits / bytes | 191,702 bits / 23,963 B (23.4 KiB) |
| Bloom hashes | 7 |
| Bloom bit density | 0.3175 |
| Bloom target / estimated FPR | 1.0% / 0.032% |
| Bloom inserts / new candidates | 10,440 / 10,440 |
| Bloom false positives / observed FPR | 2 / 0.019% |
| Ingest time / PHP peak | 0.246 s / 1.79 MiB |
| Ingest allocator delta | 1.42 MiB |
| Report records | 10,442 |
| Report runs / passes | 10 / 2 |
| Report read / written | 948,765 B / 948,765 B |
| Report logical I/O | 1,897,530 B (1.81 MiB) |
| Report comparisons | 64,198 |
| Report sort time / PHP peak | 0.088 s / 0.45 MiB |
| Report file size | 316,255 B (309 KiB) |

The exact distinct count is 10,442, but Bloom inserted 10,440 candidates and
missed two first occurrences: those two are the measured false positives. The
filter was sized for 20,000 possible URLs, while the Zipf sample realized only
10,442, so it is oversized; the target 1% is not the observed rate. The report
keeps the target, formula estimate and observation separate.

The top ten report entries were:

| Rank | URL | Requests |
|---:|---|---:|
| 1 | `https://example.test/p/1` | 14,608 |
| 2 | `https://example.test/p/2` | 6,836 |
| 3 | `https://example.test/p/3` | 4,295 |
| 4 | `https://example.test/p/4` | 3,208 |
| 5 | `https://example.test/p/5` | 2,370 |
| 6 | `https://example.test/p/6` | 1,988 |
| 7 | `https://example.test/p/7` | 1,675 |
| 8 | `https://example.test/p/8` | 1,510 |
| 9 | `https://example.test/p/9` | 1,280 |
| 10 | `https://example.test/p/10` | 1,144 |

The 10,442 aggregated records fit in 10 initial runs at 32 KiB and need two
merge passes. The report is sorted by count descending, then URL ascending; the
full file remains at `var/results/log-pipeline-report.txt`. `TopKHeap` is exact
and keeps a count for every distinct URL, so its count map is not bounded by K;
the scenario reports the heap and map together rather than pretending the
bounded heap is a bounded-memory sketch.

There is no median or p95 here. The scenario is a reproducible integration
check; rerun it for a new local observation rather than treating these as
universal timings.
