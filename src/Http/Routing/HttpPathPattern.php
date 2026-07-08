<?php

declare(strict_types=1);

namespace BlackOps\Http\Routing;

final readonly class HttpPathPattern
{
    private string $regex;

    public function __construct(string $path)
    {
        $this->regex = $this->compile($path);
    }

    /**
     * @return array<string, string>|null
     */
    public function match(string $path): ?array
    {
        /** @var array<array-key, string> $matches */
        $matches = [];

        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }

        return $this->namedMatches($matches);
    }

    private function compile(string $path): string
    {
        $parts = explode(separator: '/', string: trim($path, characters: '/'));
        $compiled = [];

        foreach ($parts as $part) {
            $matches = [];

            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $part, $matches) === 1) {
                $compiled[] = '(?P<' . $matches[1] . '>[^/]+)';
                continue;
            }

            $compiled[] = preg_quote(str: $part, delimiter: '#');
        }

        $pattern = '/' . implode('/', $compiled);

        return '#^' . $pattern . '$#';
    }

    /**
     * @param array<array-key, string> $matches
     *
     * @return array<string, string>
     */
    private function namedMatches(array $matches): array
    {
        $parameters = [];

        foreach ($matches as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $parameters[$key] = rawurldecode($value);
        }

        return $parameters;
    }
}
