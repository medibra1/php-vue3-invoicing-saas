<?php

declare(strict_types=1);

namespace App\Core\Router;

/**
 * Represents a single route: HTTP method, URI (potentially with dynamic
 * parameters like {id}), target handler, and route-specific middleware
 * to run before reaching that handler.
 *
 * The URI-to-regex compilation happens once, at construction time, to
 * avoid redoing the work on every incoming request.
 */
final class Route
{
    /** @var string[] Names of dynamic parameters extracted from the URI, e.g. ['id'] */
    public array $paramNames = [];

    /** Compiled regular expression used for matching */
    public string $pattern;

    /**
     * @param array{0: class-string, 1: string} $handler [Controller::class, 'method']
     * @param array<int, class-string|array{0: class-string, 1: mixed}> $middlewares
     *        Plain class-string, or a `[class-string, $parameter]` tuple
     *        for middleware needing per-route config (see MiddlewarePipeline).
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $handler,
        public readonly array $middlewares = []
    ) {
        $this->pattern = $this->compile($uri);
    }

    /**
     * Turns /invoices/{id} into #^/invoices/(?P<id>[^/]+)$#
     * Named groups (?P<id>...) let us pull values directly by name from
     * preg_match, without manual mapping.
     */
    private function compile(string $uri): string
    {
        $pattern = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            function (array $matches): string {
                $this->paramNames[] = $matches[1];
                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $uri
        );

        return '#^' . $pattern . '$#';
    }

    /**
     * Tests whether this route matches the given URI.
     *
     * @return array<string, string>|false The extracted parameters, or false if no match.
     */
    public function matches(string $uri): array|false
    {
        if (preg_match($this->pattern, $uri, $matches) !== 1) {
            return false;
        }

        // preg_match returns both numeric keys (0, 1, 2...) and named
        // keys (id, tenant...); we only keep the named ones.
        return array_filter(
            $matches,
            static fn ($key) => is_string($key),
            ARRAY_FILTER_USE_KEY
        );
    }
}
