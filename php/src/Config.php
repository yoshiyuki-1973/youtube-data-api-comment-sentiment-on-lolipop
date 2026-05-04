<?php

declare(strict_types=1);

class Config
{
    private static array $data = [];
    private static bool $loaded = false;

    public static function load(string $configFile): void
    {
        if (self::$loaded) {
            return;
        }
        if (!file_exists($configFile)) {
            throw new RuntimeException("設定ファイルが見つかりません: {$configFile}");
        }
        self::$data = require $configFile;
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }

    public static function require(string $key): string
    {
        $value = self::$data[$key] ?? null;
        if ($value === null || $value === '') {
            throw new RuntimeException("必須設定値が未設定です: {$key}");
        }
        return (string)$value;
    }
}
