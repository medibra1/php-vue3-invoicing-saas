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
