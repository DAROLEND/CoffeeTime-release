<?php
/**
 * Coffee Time — .env loader
 *
 * Reads key=value pairs from .env and sets them via putenv() / $_ENV.
 * Call load_env() exactly once, as early as possible (before any other include).
 *
 * Rules:
 *  - Lines starting with # are comments
 *  - Empty lines are ignored
 *  - Inline comments (#) after the value are NOT supported (keep values clean)
 *  - Surrounding single or double quotes are stripped
 *  - Already-set environment variables are NOT overwritten
 *    (lets the server's real env vars take priority over .env)
 */

if (!function_exists('load_env')) {
    function load_env(string $path): void {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and lines without '='
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip surrounding quotes
            if (
                strlen($value) >= 2 &&
                (($value[0] === '"'  && $value[-1] === '"') ||
                 ($value[0] === '\'' && $value[-1] === '\''))
            ) {
                $value = substr($value, 1, -1);
            }

            // Only set if not already defined by the real environment
            if ($key !== '' && getenv($key) === false && !isset($_ENV[$key])) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Auto-load when this file is included
load_env(dirname(__DIR__) . '/.env');
