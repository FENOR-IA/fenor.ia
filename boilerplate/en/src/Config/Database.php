<?php

class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $driver = $_ENV['DB_DRIVER'] ?? 'pgsql';
            $host   = $_ENV['DB_HOST']   ?? '127.0.0.1';
            $port   = $_ENV['DB_PORT']   ?? '5432';
            $name   = $_ENV['DB_NAME']   ?? 'fenor';
            $schema = $_ENV['DB_SCHEMA'] ?? 'public';
            $user   = $_ENV['DB_USER']   ?? '';
            $pass   = $_ENV['DB_PASS']   ?? '';

            $dsn = $driver === 'mysql'
                ? "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4"
                : "pgsql:host=$host;port=$port;dbname=$name";

            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            if ($driver === 'pgsql') {
                self::$pdo->exec("SET search_path TO \"$schema\"");
            }
        }

        return self::$pdo;
    }
}
