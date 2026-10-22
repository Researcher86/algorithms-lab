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

make benchmark ARGS="demo"
make benchmark ARGS="hash-table --repetitions=5"
make benchmark ARGS="bloom --repetitions=5"
make benchmark ARGS="cache --repetitions=5"
make benchmark ARGS="hash-table --format=json --output=var/results/hash-table.json"
```

Every suite records the environment block. Text is for reading, JSON for
keeping, CSV for comparing runs in a spreadsheet. The `demo` suite is the
smoke test; `hash-table`, `bloom` and `cache` are the chapter suites.

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
