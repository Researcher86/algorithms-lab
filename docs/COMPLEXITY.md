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

## Status

Phase 1 defines the instrumentation and the fit helper. Chapter 01 publishes
measured counters and memory; its time suite has one size, so it does not claim
a time fit. Chapter 02 adds a counter-backed O(1) pointer claim and measured
hit-ratio behavior, but also has one benchmark size. Later chapters add
multi-size fits where the question requires one.
