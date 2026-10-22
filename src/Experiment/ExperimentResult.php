<?php

declare(strict_types=1);

namespace AlgorithmsLab\Experiment;

/**
 * A finished experiment run: what ran, with which options, and what it found.
 */
final readonly class ExperimentResult
{
    public function __construct(
        public string $name,
        public string $description,
        /** @var array<string, int|float|string> */
        public array $options,
        /** @var array<string, int|float|string|bool|null> */
        public array $measurements,
        public float $elapsedSeconds,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'experiment' => $this->name,
            'description' => $this->description,
            'options' => (object) $this->options,
            'measurements' => (object) $this->measurements,
            'elapsed_seconds' => $this->elapsedSeconds,
        ];
    }
}
