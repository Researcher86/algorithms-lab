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
