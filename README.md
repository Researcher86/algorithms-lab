# Algorithms Lab

**[🧪 Engineering Lab](https://github.com/Researcher86/engineering-lab-book)** · Algorithms · 7 chapters

> An executable laboratory for complexity, memory, and real-data behavior:
> not just solving problems, but measuring where an algorithm stops being a
> textbook answer.

This repository is the code behind the seven chapters of
[Algorithms Lab](https://github.com/Researcher86/engineering-lab-book/tree/master/algorithms-lab).
The project is built in phases; the Docker environment is complete and the
build journal, including the full plan for the remaining phases, lives in
[docs/PHASES.md](docs/PHASES.md).

---

## 30-second demo

Requires Docker. Nothing is installed on the host.

```bash
make build       # build the php:8.5-cli image with the project extensions
make install     # composer install inside the container
make test        # PHPUnit
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
| 01 | Hash Table and Bloom Filter | planned |
| 02 | LRU / LFU Cache | planned |
| 03 | Graph Algorithms | planned |
| 04 | B-Tree and Search | planned |
| 05 | Consistent Hashing | planned |
| 06 | Top-K and Stream Processing | planned |
| 07 | External Sorting | planned |

Phase 0 is complete: the container and the three checks run identically
locally and in CI. Phase 1 adds the foundation and the experiment/benchmark
harness; the chapters then follow one phase at a time.

## Documentation

- [docs/PHASES.md](docs/PHASES.md) — the build journal and full plan: phases, goals and definitions of done.
- [docs/DECISIONS.md](docs/DECISIONS.md) — dated technical decisions and trade-offs.
- [docs/BENCHMARKS.md](docs/BENCHMARKS.md) — how measurements are taken and read.
- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — the harness and module map.
- [docs/COMPLEXITY.md](docs/COMPLEXITY.md) — theory, counters and measured fits.

## Project structure

```text
algorithms-lab/
├── bin/                  # experiment and benchmark entry points (Phase 1)
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

The first real code in the repository is
`src/Foundation/DeterministicRandom.php`, the seeded generator the datasets
will use. It is covered by `tests/Foundation/DeterministicRandomTest.php`.

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
