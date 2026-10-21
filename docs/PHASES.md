# Algorithms Lab — How It Was Built

The project is built from [`PLAN.md`](../PLAN.md). This document is the build
journal: every phase has a goal, the tasks that made it real, a definition of
done, and the tests that hold it. The forward-looking plan is folded into this
file once Phase 0 is finished, following the convention of the sibling
projects.

Every completed phase is a logical commit to `master`. Work is marked done only
after it passes in the container:

```bash
make test
make analyse
make format-check
```

## Final Architecture (target)

```text
Experiments                    Benchmarks
(experiments/NN-topic)         (benchmarks/*.php)
        │                              │
        └──────────────┬───────────────┘
                       ▼
              Instrumentation
       counters · stopwatch · memory meter
                       │
                       ▼
              Implementations
   hash table · bloom · cache · graph · tree
   consistent hash · stream · external sort
                       │
                       ▼
                 Explanations
        docs (EN) · book chapters (RU)
```

## Core Principles

1. **Measure before optimizing.** No claim about complexity or memory is
   accepted without a measurement or a counter.
2. **A baseline for every structure.** A naive implementation or a PHP built-in
   is always there to compare against.
3. **Counters before stopwatches.** Deterministic operation counts validate
   complexity; wall-clock time is the secondary, noisier metric.
4. **Reproducibility is part of the result.** Seed, PHP version, OPcache/JIT,
   Xdebug mode, memory limit and git commit travel with every number.
5. **Real and adversarial data.** Zipf, sorted, reversed, duplicated and
   collision-heavy inputs are first-class, not an afterthought.
6. **The container is the environment.** Development, CI and book numbers all
   run through Docker and the same make targets.
7. **The book is written from the code.** Chapters link to pinned files and
   quote real output; the checker in the book repository enforces it.

## Phase 0 — Docker environment 🚧

### Goal

A reproducible environment in which every later experiment runs identically,
with no PHP, Composer or extensions installed on the host.

### Tasks

- [x] `Dockerfile` on `php:8.5-cli` with `pcntl`, `posix`, Composer, Xdebug
      3.5.3 (built from the pinned GitHub tag, off by default), `strace`,
      `time`, `procps`, `htop`.
- [x] PHP config: `memory_limit=512M`, `opcache.enable_cli=0`, UTC; tests may
      lower the limit in a child process.
- [x] `docker-compose.yml` with one `php` service and the project mounted at
      `/app`.
- [x] `Makefile` wrappers: `up`, `down`, `build`, `shell`, `htop`, `install`,
      `test`, `analyse`, `format`, `format-check`, `check`.
- [x] `composer.json` for `researcher86/algorithms-lab`, PSR-4
      `AlgorithmsLab\`, PHPUnit, PHPStan level 8, PHP-CS-Fixer.
- [x] `phpunit.xml`, `phpstan.neon`, `.php-cs-fixer.dist.php`.
- [x] `.gitignore` for vendor, tool caches, `var/`, IDE and drafts.
- [x] GitHub Actions workflow that runs the same make targets in Docker.
- [x] `DocumentationTest` that checks docs links and backtick paths.
- [x] First real class and test: `src/Foundation/DeterministicRandom.php`.
- [ ] Fold `PLAN.md` into this document and delete it from the root.
- [ ] Initial commit and public remote `Researcher86/algorithms-lab`.

### Definition of Done

```bash
make build
make install
make test
make analyse
make format-check
```

all green, and CI reproduces the same on GitHub.

### Tests

- `tests/Foundation/DeterministicRandomTest.php` — same seed, same sequence;
  bounds; invalid ranges.
- `tests/DocumentationTest.php` — README links every document; relative links
  resolve; paths mentioned in backticks exist.

## Phase 1 — Foundation and the measurement harness

Planned. The full task list lives in [`PLAN.md`](../PLAN.md).

## Phases 2–8 — Chapters 01–07

Planned. Each chapter follows the same definition of done: implementation,
oracle/differential tests, experiments, benchmark suite, measured tables and
book material.

## Phase 9 — Integration scenarios

Planned: a cache tier, a log pipeline and a mini index that combine the
structures the way the sibling systems do.

## Phase 10 — Release and book handoff

Planned: tag `v1.0.0`, pin the chapters, extend the book checker to seven
chapters, update the book README.
