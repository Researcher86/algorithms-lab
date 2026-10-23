<?php

declare(strict_types=1);

namespace AlgorithmsLab\Experiment;

use InvalidArgumentException;

/**
 * Validated options for an experiment.
 *
 * The set is intentionally small and shared by every experiment: `--seed`
 * means the same thing everywhere, and an unknown name is refused rather than
 * ignored. Values keep their original type so JSON reports show `seed` as a
 * string and `load-factor` as a float.
 */
final readonly class Options
{
    public const KNOWN = [
        'size' => 'elements in a structure',
        'elements' => 'elements in a generated dataset',
        'iterations' => 'times to repeat a measured operation',
        'capacity' => 'cache capacity or bucket count',
        'load-factor' => 'target load factor, greater than 0 and at most 1',
        'input-mb' => 'input size in MiB for an external sort',
        'chunk-mb' => 'chunk size in MiB for an external sort',
        'fan-in' => 'maximum number of runs merged at once',
        'requests' => 'number of requests in an access-log scenario',
        'urls' => 'distinct URL alphabet size in an access-log scenario',
        'top' => 'number of entries in a top-K result',
        'seed' => 'seed for a reproducible dataset',
    ];

    private const INTEGER_OPTIONS = [
        'size',
        'elements',
        'iterations',
        'capacity',
        'input-mb',
        'chunk-mb',
        'fan-in',
        'requests',
        'urls',
        'top',
    ];

    /**
     * @param array<string, int|float|string> $values
     */
    private function __construct(
        private array $values,
    ) {
    }

    /**
     * @param array<string, string> $raw
     *
     * @throws InvalidArgumentException on an unknown option or an invalid value
     */
    public static function fromStrings(array $raw): self
    {
        $values = [];

        foreach ($raw as $name => $value) {
            if (!isset(self::KNOWN[$name])) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown option --%s. Known options: %s',
                    $name,
                    implode(', ', array_keys(self::KNOWN)),
                ));
            }

            if (in_array($name, self::INTEGER_OPTIONS, true)) {
                if (preg_match('/^\d+$/', $value) !== 1 || (int) $value < 1) {
                    throw new InvalidArgumentException(sprintf(
                        '--%s must be a positive integer, got "%s"',
                        $name,
                        $value,
                    ));
                }

                $values[$name] = (int) $value;

                continue;
            }

            if ($name === 'load-factor') {
                if (!is_numeric($value) || (float) $value <= 0.0 || (float) $value > 1.0) {
                    throw new InvalidArgumentException(sprintf(
                        '--load-factor must be greater than 0 and at most 1, got "%s"',
                        $value,
                    ));
                }

                $values[$name] = (float) $value;

                continue;
            }

            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('--%s cannot be empty', $name));
            }

            $values[$name] = $value;
        }

        return new self($values);
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function size(int $default): int
    {
        return (int) ($this->values['size'] ?? $default);
    }

    public function elements(int $default): int
    {
        return (int) ($this->values['elements'] ?? $default);
    }

    public function iterations(int $default): int
    {
        return (int) ($this->values['iterations'] ?? $default);
    }

    public function capacity(int $default): int
    {
        return (int) ($this->values['capacity'] ?? $default);
    }

    public function loadFactor(float $default): float
    {
        return (float) ($this->values['load-factor'] ?? $default);
    }

    public function inputMb(int $default): int
    {
        return (int) ($this->values['input-mb'] ?? $default);
    }

    public function chunkMb(int $default): int
    {
        return (int) ($this->values['chunk-mb'] ?? $default);
    }

    public function fanIn(int $default): int
    {
        return (int) ($this->values['fan-in'] ?? $default);
    }

    public function requests(int $default): int
    {
        return (int) ($this->values['requests'] ?? $default);
    }

    public function urls(int $default): int
    {
        return (int) ($this->values['urls'] ?? $default);
    }

    public function top(int $default): int
    {
        return (int) ($this->values['top'] ?? $default);
    }

    public function seed(string $default): string
    {
        $value = $this->values['seed'] ?? $default;

        return is_string($value) ? $value : (string) $value;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->values);
    }

    /**
     * @return array<string, int|float|string>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
