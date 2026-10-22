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

make benchmark ARGS="demo"
make benchmark ARGS="hash-table --repetitions=5"
make benchmark ARGS="bloom --repetitions=5"
make benchmark ARGS="hash-table --format=json --output=var/results/hash-table.json"
```

Every suite records the environment block. Text is for reading, JSON for
keeping, CSV for comparing runs in a spreadsheet. The `demo` suite is the
smoke test; `hash-table` and `bloom` are the chapter 01 suites.

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
