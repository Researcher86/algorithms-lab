# Decisions — Algorithms Lab

A record of meaningful technical and process decisions, with the reason each
was made. New decisions are appended with a date.

## 2026-10-21 — Docker is the only development environment

- Every command runs through `make` and `docker compose exec php` in the
  project image; host PHP, Composer and extensions are not a supported setup.
- The image is `php:8.5-cli` with `pcntl`, `posix`, Composer, Xdebug and the
  diagnostics needed for benchmarks (`strace`, `time`, `procps`, `htop`).
- CI runs the same make targets in the same image. There is no second
  environment to drift from.
- The project directory is mounted at `/app`; `var/` therefore survives
  container restarts but stays out of git.

## 2026-10-21 — PHP 8.5, matching the ecosystem

- `"php": "^8.5"` matches `php-memory-lab` and `php-mini-cache`.
- The container is the guarantee that the required extensions exist; there are
  no runtime `extension_loaded()` checks in application code.

## 2026-10-21 — Xdebug is installed but off by default

- The image ships Xdebug 3.5.3 with `xdebug.mode=off`. A benchmarking lab
  cannot afford an extension that changes timings just by being loaded.
- Debugging is opt-in: `XDEBUG_MODE=debug XDEBUG_TRIGGER=1` with the
  `-debug` make targets, pointing at the host on port 9003.
- Xdebug is built from the pinned GitHub tag, not installed through PECL:
  `pecl install` returned "No releases available" and the PECL endpoint was
  intermittently a 504. The GitHub tag supports PHP 8.5, and the version is an
  explicit `ARG`, so the build stays reproducible.
- Build dependencies (`$PHPIZE_DEPS`) are installed for the compile layer and
  purged before the image is finalized.

## 2026-10-21 — 512M memory limit in the container

- The default CLI limit of 128M is too small for million-element datasets and
  graph experiments. The project sets `memory_limit=512M`.
- Tests that need to prove bounded memory (external sort, sketches) lower the
  limit explicitly in a child process with `-d memory_limit=128M`; the project
  default does not decide what those tests may use.
- `opcache.enable_cli=0` is explicit rather than inherited: the benchmark
  environment should be the same every run, and a CLI cache that appears later
  should not silently change the numbers.

## 2026-10-21 — One repository for seven chapters

- The book has one `algorithms-lab/README.md`, and the chapters share the
  foundation, instrumentation and benchmark harness.
- Splitting into seven repositories would multiply that shared code and force
  every book link to name a different repository.
- This differs from `php-systems-lab`, where each level is a separate project;
  the unit here is the book's lab, not the system level.

## 2026-10-21 — Namespace and package name

- Composer package: `researcher86/algorithms-lab`.
- PSR-4: `AlgorithmsLab\` → `src/`, `AlgorithmsLab\Tests\` → `tests/`.
- No `App\` prefix: it is accurate for one project, but this repository is
  meant to be read next to the book and the sibling labs, where the package
  name is the identity.

## 2026-10-21 — PHPStan level 8, not max

- The sibling projects use level 8. The dynamic parts of this lab (generic-ish
  containers documented with PHPDoc, dataset arrays, closures in experiment
  descriptors) would need a large baseline at max, which hides real errors.
- Paths grow to `bin/`, `experiments/` and `benchmarks/` in Phase 1.

## 2026-10-21 — No LICENSE file

- As in `php-memory-lab`: `composer.json` declares MIT; the repository does not
  carry a separate `LICENSE` file.

## 2026-10-22 — Descriptors and closures for the harness

- An experiment is a readonly `Experiment` returned by a file under
  `experiments/`; a benchmark is a readonly `Benchmark` returned in a list from
  `benchmarks/`. There is no base class and no central registry.
- Discovery keeps the list honest when files move, and descriptors stay cheap
  because the work lives in a closure that only the runner invokes.
- The alternative — abstract `run()` methods — would make every demo and
  chapter file longer for no shared behavior.

## 2026-10-22 — Unknown experiment options are refused

- `Options` is a closed set shared by every experiment; the runner checks it
  against the experiment's declared `supports` list.
- Silently ignoring `--capacity` in an experiment that never reads it is how a
  result gets published under a configuration it did not run with. The error
  names both the bad option and the options that would work.

## 2026-10-22 — Medians and spread, not a single timing

- A benchmark runs warm-ups, then repeated measurements; the report carries
  min, max, mean, median, p95 and median absolute deviation, and ops/sec is
  derived from the median.
- The median is the headline because one repetition can lose its CPU slice.
  The spread is not optional: with five samples, a p95 is a coarse statement
  and the report says so instead of implying precision.
- Memory is reported as a PHP-allocator delta (`php_delta_bytes`), not RSS.
  Chapters that involve shared or native memory will add the second view
  rather than rename this one.

## 2026-10-22 — The log-log fit is the only complexity fit

- `ComplexityFit::powerLaw()` performs ordinary least squares on logarithms and
  reports slope, intercept, R² and sample count.
- A single power law is easy to explain and easy to falsify. `n log n` will be
  tested with an explicit model in the chapters that need it; the fit is never
  presented as proof of an asymptotic class.

## 2026-10-22 — Chaining and open addressing are both first-class

- The chapter keeps `ChainingHashTable` as the readable baseline and
  `OpenAddressingHashTable` as the memory-conscious alternative. Neither is
  presented as the winner; the experiments report probes, memory and timing.
- Chaining supports a fixed-capacity mode for the distribution experiment.
  Automatic resizing remains the default, and the mode is explicit in the
  constructor rather than hidden in a benchmark.
- Open addressing keeps its capacity a power of two. Quadratic probing uses
  triangular offsets (`i(i+1)/2`), which cover every slot for a power-of-two
  capacity; double hashing uses an odd step for the same reason.
- A tombstone is not an empty slot: unsuccessful searches continue past it, and
  insertions remember the first tombstone but keep probing for an existing key.

## 2026-10-22 — Bloom parameters are reported three ways

- The filter computes `m` and `k` from the expected item count and target rate,
  then reports target, estimated and measured false-positive rates separately.
- The estimated rate comes from the actual fill; the measured rate comes from
  a deterministic set of absent queries. Printing only the target would hide a
  sizing bug, and printing only the measured rate would imply more statistical
  precision than one sample has.
- Double hashing derives all positions from two independent hashes. The filter
  cannot delete or enumerate keys; that limitation is part of the chapter, not
  an implementation detail to paper over.

## 2026-10-22 — PHPStan always starts from a fresh file cache

- PHPStan 2.3's result cache can keep a stale file list when new directories
  appear after an earlier run. `clear-result-cache` did not remove the cached
  file map in this project; `composer analyse` therefore removes
  `/tmp/phpstan` before analysis.
- At this repository's size a fresh analysis takes seconds, and a local green
  run that silently skipped new files is worse than the cost.
