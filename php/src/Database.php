<?php

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host    = Config::require('DB_HOST');
            $name    = Config::require('DB_NAME');
            $user    = Config::require('DB_USER');
            $pass    = Config::require('DB_PASS');
            $charset = Config::get('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    // テスト用：インスタンスをリセット
    public static function reset(): void
    {
        self::$instance = null;
    }
}
