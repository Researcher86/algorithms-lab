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

make benchmark ARGS="demo"
make benchmark ARGS="hash-table --repetitions=5"
make benchmark ARGS="bloom --repetitions=5"
make benchmark ARGS="cache --repetitions=5"
make benchmark ARGS="graph --repetitions=5"
make benchmark ARGS="tree --repetitions=5"
make benchmark ARGS="hash-table --format=json --output=var/results/hash-table.json"
```

Every suite records the environment block. Text is for reading, JSON for
keeping, CSV for comparing runs in a spreadsheet. The `demo` suite is the
smoke test; `hash-table`, `bloom`, `cache`, `graph` and `tree` are the chapter
suites.

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
