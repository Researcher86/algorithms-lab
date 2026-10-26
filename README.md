# Algorithms Lab

**[🧪 Engineering Lab](https://github.com/Researcher86/engineering-lab-book)** · Algorithms · 7 chapters

> An executable laboratory for complexity, memory, and real-data behavior:
> not just solving problems, but measuring where an algorithm stops being a
> textbook answer.

This repository is the code behind the seven chapters of
[Algorithms Lab](https://github.com/Researcher86/engineering-lab-book/tree/master/algorithms-lab).
The Docker environment, the measurement harness, the first seven chapters and
the log-pipeline integration scenario are complete. The code release is tagged
[`v1.0.0`](https://github.com/Researcher86/algorithms-lab/releases/tag/v1.0.0);
the build journal lives in [docs/PHASES.md](docs/PHASES.md).

---

## 30-second demo

Requires Docker. Nothing is installed on the host.

```bash
make build       # build the php:8.5-cli image with the project extensions
make install     # composer install inside the container
make test        # PHPUnit
```

The harness has one demo of each entry point:

```bash
make experiment                              # list the experiments
make experiment ARGS="demo:smoke"            # run one
make scenario                                # Bloom -> top-K -> external report
make benchmark ARGS="demo"                   # text report with environment
```

The first seven chapters are runnable as well:

```bash
make experiment ARGS="hash:load-factor --elements=20000 --seed=book"
make experiment ARGS="bloom:fpr --elements=10000 --seed=book"
make experiment ARGS="cache:zipf --seed=book"
make experiment ARGS="cache:scan --seed=book"
make experiment ARGS="graph:representations --seed=book"
make experiment ARGS="graph:dijkstra --seed=book"
make experiment ARGS="tree:height --seed=book"
make experiment ARGS="tree:range --seed=book"
make experiment ARGS="ring:remap --seed=book"
make experiment ARGS="ring:balance"
make experiment ARGS="stream:topk --seed=book"
make experiment ARGS="stream:heavy-hitters --seed=book"
make experiment ARGS="stream:all-unique"
make experiment ARGS="stream:drift --seed=book"
make experiment ARGS="sort:memory-limit --seed=book"
make experiment ARGS="sort:fan-in --seed=book"
make experiment ARGS="sort:patterns --seed=book"
make experiment ARGS="sort:io --seed=book"
make scenario
make benchmark ARGS="hash-table --repetitions=5"
make benchmark ARGS="cache --repetitions=5"
make benchmark ARGS="graph --repetitions=5"
make benchmark ARGS="tree --repetitions=5"
make benchmark ARGS="ring --repetitions=5"
make benchmark ARGS="stream --repetitions=5"
make benchmark ARGS="external-sort --repetitions=5"
```

The other two gates CI runs:

```bash
make analyse        # PHPStan level 8
make format-check   # PHP-CS-Fixer dry run
```

Open a shell in the same container with `make shell`; stop it with `make down`.

## What it does

| | |
|---|---|
| **Docker-first** | The only supported environment is the project container; `make` never calls host PHP |
| **PHP 8.5** | The same image and extensions as the sibling `php-systems-lab` projects |
| **Reproducible measurements** | Seeded datasets, operation counters, warm-up and repeated timings, environment recorded with every result |
| **Seven chapters** | Hash Table + Bloom Filter, LRU/LFU, graphs, B-Tree, consistent hashing, Top-K, external sorting |
| **Book contract** | Every chapter links to pinned code and quotes real output from this repository |

## Chapters

The order is fixed by the book. Implementation follows the same order.

| # | Chapter | Status |
|---|---|---|
| 01 | Hash Table and Bloom Filter | ✅ code and measurements |
| 02 | LRU / LFU Cache | ✅ code and measurements |
| 03 | Graph Algorithms | ✅ code and measurements |
| 04 | B-Tree and Search | ✅ code and measurements |
| 05 | Consistent Hashing | ✅ code and measurements |
| 06 | Top-K and Stream Processing | ✅ code and measurements |
| 07 | External Sorting | ✅ code and measurements |

Phase 0, Phase 1 and the code and measurements of Chapters 01–07 are complete:
the container, the foundation, the instrumentation, the experiment/benchmark
harness and the hash-table/Bloom-filter, cache, graph, tree, consistent-hash,
stream and external-sort chapters all run through `make check` locally and in
CI. The Phase 9 MVP scenario is also complete: `make scenario` runs a
deterministic access-log stream through Bloom, exact top-K and an externally
sorted report. The code is tagged `v1.0.0`; the seven Russian book chapters
are published in the
[book repository](https://github.com/Researcher86/engineering-lab-book/tree/master/algorithms-lab)
and pinned to that tag.

## Documentation

- [docs/PHASES.md](docs/PHASES.md) — the build journal and full plan: phases, goals and definitions of done.
- [docs/DECISIONS.md](docs/DECISIONS.md) — dated technical decisions and trade-offs.
- [docs/BENCHMARKS.md](docs/BENCHMARKS.md) — how measurements are taken and read.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — the harness and module map.
- [docs/COMPLEXITY.md](docs/COMPLEXITY.md) — theory, counters and measured fits.

## Project structure

```text
algorithms-lab/
├── bin/                  # experiment and benchmark entry points
├── src/                  # implementations, instrumentation, harness
├── experiments/          # one runnable experiment per question
├── benchmarks/           # repeated measurements per topic
├── datasets/             # generators and small fixtures
├── tests/                # mirrors src/, plus documentation checks
├── docs/                 # engineering documentation (English)
├── var/                  # generated results and datasets (gitignored)
├── Dockerfile
├── docker-compose.yml
├── Makefile
└── composer.json
```

The foundation primitives live under `src/Foundation/`: the seeded
`DeterministicRandom`, a comparison-counting `Comparator`, a binary heap, an
indexed decrease-key heap, a bit array, the hashers, union-find with optional
path compression and rank, and a deterministic `ZipfDistribution`.
`src/Instrumentation/` adds counters, `hrtime` stopwatches, a PHP-memory meter,
the `Measure` facade and a log-log complexity fit. Chapter 01 adds
`src/HashTable/` and `src/BloomFilter/`; Chapter 02 adds `src/Cache/` with
LRU, LFU, heap-LFU and two baselines; Chapter 03 adds `src/Graph/` with four
representations, traversals, shortest paths, topological sort, MST and
deterministic generators; Chapter 04 adds `src/Tree/` with a B-tree, a
linked-leaf B+ tree, sorted/array baselines, a page-cost model and reproducible
key sets; Chapter 05 adds `src/ConsistentHash/` with a virtual-node ring, a
rendezvous-hash alternative and a modulo baseline; Chapter 06 adds
`src/Stream/` with an exact top-K heap, QuickSelect, Count-Min, Space-Saving,
Misra-Gries and reproducible stream generators; Chapter 07 adds
`src/ExternalSort/` with a chunk-sort-spill-merge sorter, byte-bounded chunk
reader, stable k-way merge, deterministic pattern generator and logical-I/O
statistics. `src/Integration/` adds the Phase 9 MVP: a deterministic Zipf URL
stream, a Bloom first-seen pass, exact top-K counting and an externally sorted
daily report. The experiment and benchmark entry points are discoverable: drop
a descriptor under `experiments/` or `benchmarks/` and it appears in the list
without editing a central registry.

## How this project relates

- **[Engineering Lab book](https://github.com/Researcher86/engineering-lab-book)** —
  the Russian narrative: one chapter per topic, code excerpts pinned to a tag,
  experiments quoted with their real output.
- **[PHP Systems Lab](https://github.com/Researcher86/php-systems-lab)** — the
  sibling projects that turn the same mechanisms into systems: caches
  (`php-mini-cache`), B-trees (`php-mini-database`), filters and ranking
  (`php-mini-search`).
- **Algorithms Lab** — the place where the cost of those mechanisms is measured
  in isolation, before they disappear inside a server.

## License

MIT.
