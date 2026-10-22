<?php

declare(strict_types=1);

namespace AlgorithmsLab\Benchmark\Formatter;

use AlgorithmsLab\Benchmark\BenchmarkReport;

/** Renders a finished report. One implementation per output format. */
interface Formatter
{
    public function format(BenchmarkReport $report): string;
}
