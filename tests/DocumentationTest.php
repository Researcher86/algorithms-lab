<?php

declare(strict_types=1);

namespace AlgorithmsLab\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The documentation is half of what this project is, and it goes stale the
 * same way code does - by the thing it describes moving. These checks are
 * mechanical because eyes are what miss a renamed file.
 */
final class DocumentationTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    public function testEveryDocumentIsLinkedFromTheReadme(): void
    {
        $readme = (string) file_get_contents(self::ROOT . '/README.md');

        foreach ($this->documents() as $document) {
            self::assertStringContainsString(
                'docs/' . basename($document),
                $readme,
                basename($document) . ' exists but nothing in README points at it',
            );
        }
    }

    public function testEveryRelativeLinkResolves(): void
    {
        foreach ([...$this->documents(), self::ROOT . '/README.md'] as $document) {
            $directory = dirname($document);

            foreach ($this->linksIn($document) as $target) {
                // A bare "#anchor" link points inside the same document and
                // has no file to check.
                $path = strtok($target, '#');

                if ($path === false || str_starts_with($target, '#')) {
                    continue;
                }

                self::assertFileExists(
                    $directory . '/' . $path,
                    sprintf('%s links to %s, which does not exist', basename($document), $target),
                );
            }
        }
    }

    /**
     * A path in backticks reads as a claim that the file is there. Renaming a
     * file is exactly the change that leaves such a claim behind.
     */
    public function testEveryPathMentionedInBackticksExists(): void
    {
        foreach ([...$this->documents(), self::ROOT . '/README.md'] as $document) {
            $contents = (string) file_get_contents($document);
            $matches = [];
            preg_match_all(
                '/`((?:src|tests|bin|benchmarks|experiments|docs)\/[A-Za-z0-9_.\/-]+\.(?:php|md|neon|json))`/',
                $contents,
                $matches,
            );

            foreach (array_unique($matches[1]) as $path) {
                self::assertFileExists(
                    self::ROOT . '/' . $path,
                    sprintf('%s mentions %s, which does not exist', basename($document), $path),
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function documents(): array
    {
        return glob(self::ROOT . '/docs/*.md') ?: [];
    }

    /**
     * @return list<string>
     */
    private function linksIn(string $document): array
    {
        $matches = [];
        preg_match_all('/\[[^\]]*\]\(([^)]+)\)/', (string) file_get_contents($document), $matches);

        return array_values(array_filter(
            $matches[1],
            static fn (string $target): bool => !str_starts_with($target, 'http'),
        ));
    }
}
