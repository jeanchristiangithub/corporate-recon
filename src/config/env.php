<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string
    {
        static $loaded = false;
        static $vars = [];

        if (!$loaded) {
            $root = dirname(__DIR__, 2);
            $envPath = $root . DIRECTORY_SEPARATOR . '.env';

            if (is_readable($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                        continue;
                    }

                    [$name, $value] = explode('=', $trimmed, 2);
                    $name = trim($name);
                    $value = trim($value);

                    if ($name !== '') {
                        $vars[$name] = $value;
                    }
                }
            }

            $loaded = true;
        }

        return $vars[$key] ?? $default;
    }
}
