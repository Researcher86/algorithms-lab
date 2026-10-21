# Architecture — Algorithms Lab

This document maps the pieces that every chapter shares. The chapter-specific
implementations are added one at a time; the harness below is what makes their
measurements comparable.

## The flow

```text
experiments/NN-topic/*.php          benchmarks/*.php
        │                                  │
        │ returns an Experiment            │ returns a list of Benchmark
        ▼                                  ▼
  ExperimentRegistry                BenchmarkRunner
        │                                  │
        ▼                                  ▼
  ExperimentRunner                  Timings + Environment
        │                                  │
        ▼                                  ▼
  Output (text / JSON)              Formatter (text / JSON / CSV)
        │                                  │
        └──────────────┬───────────────────┘
                       ▼
              docs/BENCHMARKS.md
              book chapter (pinned output)
```

## Module map

| Module | Responsibility | Depends on |
|---|---|---|
| Foundation | Data structures and small primitives with no I/O: heap, bit array, hashers, comparator, seeded random, union-find | nothing |
| Instrumentation | Counters, stopwatch, memory meter and the Measure facade | Foundation |
| Experiment | Descriptor, discovery, option validation, output, runner | Instrumentation |
| Benchmark | Descriptor, repetitions, timings, environment, formatters | Instrumentation |
| Cli | `--name=value` parsing shared by both entry points | nothing |
| Chapter modules | Hash table, Bloom, cache, graph, tree, ring, stream, external sort | Foundation, Instrumentation |

## Experiment contract

An experiment is a PHP file under `experiments/*/*.php` that returns an
`Experiment` object. The file must not perform the experiment at include time:
the registry loads every descriptor to build the list, so allocating a large
dataset there would make `make experiment` slow and surprising.

```php
return new Experiment(
    name: 'demo:smoke',
    description: 'Checks the harness end to end.',
    supports: ['elements', 'seed'],
    run: static function (Options $options, Output $output): void {
        // Print prose, record named measurements.
    },
);
```

The name is stable and namespaced (`topic:question`). The `supports` list is
the complete set of options the closure reads; the runner refuses anything
else. Measurements are scalars and are what JSON output carries.

## Benchmark contract

A benchmark file under `benchmarks/` returns a list of `Benchmark` objects.
The iteration count belongs to the benchmark, because one operation may need
thousands of calls to rise above timer noise while another needs one. The
runner adds warm-up and repetitions on top.

A benchmark reports the environment with every result:

- PHP version, OS, kernel, architecture and CPU count;
- `memory_limit`, cgroup memory limit;
- OPcache and JIT state;
- Xdebug mode;
- allocator;
- git commit.

None of these fields is optional. A number without them describes a machine,
not an algorithm.

## Where the code lives

- `src/Foundation/` — primitives shared by chapters.
- `src/Instrumentation/` — measurement tools with no domain knowledge.
- `src/Experiment/` and `src/Benchmark/` — the harness.
- `src/Cli/` — argument parsing.
- Chapter modules (`src/HashTable/`, `src/Cache/`, and so on) — implementations
  and their baselines. Each has a matching test directory.
- `experiments/` — one runnable question per file, grouped by chapter.
- `benchmarks/` — one suite per file, grouped by topic.

## Design rules

1. **Descriptors, not base classes.** Experiments and benchmarks are small
   readonly objects with closures. A chapter is readable top to bottom, and
   the harness does not impose a template method on it.
2. **Discovery, not a hand-written registry.** A renamed or moved experiment
   changes no central list.
3. **Refuse unknown options.** A silently ignored option is how a result gets
   published under a configuration it never ran with.
4. **Measure in isolation.** No benchmark includes dataset generation unless
   the question is about dataset generation; warm-up is discarded.
5. **Counters are domain-specific.** The harness provides the container; each
   chapter decides which operations to count.
