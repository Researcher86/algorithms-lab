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

## Status

Phase 1 defines the instrumentation and the fit helper. Chapter 01 is the first
to publish a table; until then this document contains no measured numbers.
