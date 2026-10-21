# Benchmarks: How They Are Run, and How to Read Them

The harness is built in Phase 1. This document is the contract it must satisfy;
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

## Commands (Phase 1)

```bash
make benchmark ARGS="hash-table"
make benchmark ARGS="cache --repetitions=9"
make benchmark ARGS="graph --format=json --output=var/results/graph.json"
```

Every suite records the environment block and can emit text, JSON or CSV.

## Container numbers

The project runs in Docker on macOS during development. Docker Desktop is a
virtualized Linux machine: absolute timings are noisier and not comparable with
a native Linux host. Relative comparisons, operation counters and memory
measurements remain useful. Numbers quoted in the book are taken in one fixed
Linux environment (CI or a dedicated host) and always include the block above.

## Results

Not measured yet. Phase 1 adds the demo suite; each chapter adds its own table.
