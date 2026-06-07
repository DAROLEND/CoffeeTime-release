<?php
// Reads .env key=value pairs into putenv() / $_ENV (skips already-set env vars)

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

            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if (
                strlen($value) >= 2 &&
                (($value[0] === '"'  && $value[-1] === '"') ||
                 ($value[0] === '\'' && $value[-1] === '\''))
            ) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '' && getenv($key) === false && !isset($_ENV[$key])) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

load_env(dirname(__DIR__) . '/.env');
