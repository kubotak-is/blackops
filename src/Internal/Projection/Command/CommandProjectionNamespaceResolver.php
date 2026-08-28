<?php

declare(strict_types=1);

namespace BlackOps\Internal\Projection\Command;

use Symfony\Component\Console\Exception\NamespaceNotFoundException;

/**
 * Resolves namespaces from command metadata without loading command objects.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class CommandProjectionNamespaceResolver
{
    /**
     * @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $records
     * @return list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}>
     */
    public function select(array $records, ?string $namespace = null): array
    {
        $records = $this->canonicalRecords($records);

        if ($namespace === null) {
            return $this->sort($records);
        }

        $resolvedNamespace = $this->resolveNamespace($namespace, $this->namespaces($records));
        $depth = substr_count($resolvedNamespace, needle: ':') + 1;
        $selected = [];

        foreach ($records as $record) {
            foreach ([$record['name'], ...$record['aliases']] as $name) {
                if ($this->namespaceAtDepth($name, $depth) !== $resolvedNamespace) {
                    continue;
                }

                $selected[$record['name']] = $record;
                break;
            }
        }

        return $this->sort(array_values($selected));
    }

    /**
     * @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $records
     * @return list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}>
     */
    private function canonicalRecords(array $records): array
    {
        $canonical = [];
        foreach ($records as $record) {
            $canonical[$record['name']] ??= $record;
        }

        return array_values($canonical);
    }

    /**
     * @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $records
     * @return list<string>
     */
    private function namespaces(array $records): array
    {
        $namespaces = [];
        foreach ($records as $record) {
            if ($record['hidden']) {
                continue;
            }

            foreach ([$record['name'], ...$record['aliases']] as $name) {
                $parts = explode(':', $name);
                $prefix = [];
                foreach (array_slice($parts, offset: 0, length: -1) as $part) {
                    $prefix[] = $part;
                    $namespace = implode(':', $prefix);
                    $namespaces[$namespace] ??= true;
                }
            }
        }

        return array_keys($namespaces);
    }

    /**
     * @param list<string> $allNamespaces
     */
    private function resolveNamespace(string $namespace, array $allNamespaces): string
    {
        $expression = implode('[^:]*:', array_map('preg_quote', explode(':', $namespace))) . '[^:]*';
        $matches = [];
        foreach ($allNamespaces as $candidate) {
            if (preg_match('{^' . $expression . '}', $candidate) !== 1) {
                continue;
            }

            $matches[] = $candidate;
        }

        if ($matches === []) {
            $alternatives = $this->findAlternatives($namespace, $allNamespaces);
            $message = sprintf('There are no commands defined in the "%s" namespace.', $namespace);
            if ($alternatives !== []) {
                $message .= "\n\nDid you mean one of these?\n    ";
                if (count($alternatives) === 1) {
                    $message = str_replace(
                        search: "\n\nDid you mean one of these?",
                        replace: "\n\nDid you mean this?",
                        subject: $message,
                    );
                }
                $message .= implode("\n    ", $alternatives);
            }

            throw new NamespaceNotFoundException($message, $alternatives);
        }

        if (in_array($namespace, $matches, strict: true)) {
            return $namespace;
        }

        if (count($matches) > 1) {
            throw new NamespaceNotFoundException(
                sprintf(
                    "The namespace \"%s\" is ambiguous.\nDid you mean one of these?\n%s.",
                    $namespace,
                    $this->abbreviationSuggestions($matches),
                ),
                $matches,
            );
        }

        return $matches[0];
    }

    /**
     * Mirrors Symfony Console's segment-aware suggestion scoring for namespaces.
     *
     * @param list<string> $allNamespaces
     * @return list<string>
     */
    private function findAlternatives(string $namespace, array $allNamespaces): array
    {
        $threshold = 1000;
        /** @var array<string, int> $alternatives */
        $alternatives = [];

        $collectionParts = [];
        foreach ($allNamespaces as $item) {
            $collectionParts[$item] = explode(':', $item);
        }

        foreach (explode(':', $namespace) as $index => $subname) {
            foreach ($collectionParts as $collectionName => $parts) {
                $score = $alternatives[$collectionName] ?? null;
                if (!array_key_exists($index, $parts)) {
                    if ($score !== null) {
                        $alternatives[$collectionName] = $score + $threshold;
                    }

                    continue;
                }

                $distance = levenshtein($subname, $parts[$index]);
                if ($distance <= (strlen($subname) / 3) || $subname !== '' && str_contains($parts[$index], $subname)) {
                    $alternatives[$collectionName] = $score !== null ? $score + $distance : $distance;
                    continue;
                }

                if ($score !== null) {
                    $alternatives[$collectionName] = $score + $threshold;
                }
            }
        }

        foreach ($allNamespaces as $item) {
            $distance = levenshtein($namespace, $item);
            if ($distance <= (strlen($namespace) / 3) || str_contains($item, $namespace)) {
                $score = $alternatives[$item] ?? null;
                $alternatives[$item] = $score !== null ? $score - $distance : $distance;
            }
        }

        /** @var array<string, int> $alternatives */
        $alternatives = array_filter($alternatives, static fn(int $distance): bool => $distance < (2 * $threshold));
        ksort($alternatives, SORT_NATURAL | SORT_FLAG_CASE);

        return array_keys($alternatives);
    }

    /** @param list<string> $matches */
    private function abbreviationSuggestions(array $matches): string
    {
        return '    ' . implode("\n    ", $matches);
    }

    private function namespaceAtDepth(string $name, int $depth): string
    {
        $parts = explode(':', $name, -1);
        if (count($parts) < $depth) {
            return '';
        }

        return implode(':', array_slice($parts, offset: 0, length: $depth));
    }

    /**
     * @param list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}> $records
     * @return list<array{name: string, aliases: list<string>, description: string, origin: string, hidden: bool}>
     */
    private function sort(array $records): array
    {
        usort($records, static fn(array $left, array $right): int => strcmp($left['name'], $right['name']));

        return $records;
    }
}
