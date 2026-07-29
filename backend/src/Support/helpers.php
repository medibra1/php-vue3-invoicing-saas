<?php

declare(strict_types=1);

// Global helper functions, autoloaded by Composer ("files" in composer.json).

if (!function_exists('env')) {
    /**
     * Reads a value from the environment ($_ENV or getenv()), falling
     * back to $default. Throws when the variable is required (no
     * default given) and absent, so misconfiguration fails loudly at
     * boot instead of surfacing as a confusing error once the value is
     * actually used (e.g. a PDO connection failure with no DB_HOST).
     */
    function env(string $key, ?string $default = null): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            if ($default === null) {
                throw new \RuntimeException("Missing required environment variable [{$key}].");
            }

            return $default;
        }

        return (string) $value;
    }
}

if (!function_exists('loadEnvFile')) {
    /**
     * Minimal .env loader: one KEY=VALUE per line, `#` comments and
     * blank lines skipped, no quoting/escaping/interpolation. Existing
     * environment variables (e.g. set by Docker/CI) are never
     * overridden — a real env var always wins over the file. Silently
     * does nothing if the file doesn't exist (e.g. in CI, where config
     * comes from real env vars instead).
     */
    function loadEnvFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if ($key === '' || array_key_exists($key, $_ENV) || getenv($key) !== false) {
                continue;
            }

            $value = trim($value);
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}
