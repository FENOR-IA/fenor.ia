<?php

function fenorDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $env = fenorEnv();

    $driver = $env['DB_DRIVER'] ?? 'pgsql';
    $host   = $env['DB_HOST']   ?? '127.0.0.1';
    $port   = $env['DB_PORT']   ?? ($driver === 'mysql' ? '3306' : '5432');
    $name   = $env['DB_NAME']   ?? 'fenor';
    $user   = $env['DB_USER']   ?? '';
    $pass   = $env['DB_PASS']   ?? '';

    $dsn = $driver === 'mysql'
        ? "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4"
        : "pgsql:host=$host;port=$port;dbname=$name";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function fenorEnv(): array {
    static $env = null;
    if ($env !== null) return $env;

    $env = [];
    $file = file_exists('/etc/fenor/.env')
        ? '/etc/fenor/.env'
        : dirname(__DIR__) . '/.env';

    if (!file_exists($file)) return $env;

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strncmp(trim($line), '#', 1) === 0) continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $env[trim($k)] = trim($v);
    }

    return $env;
}

function fenorSetting(string $key, string $default = ''): string {
    try {
        $stmt = fenorDB()->prepare('SELECT value FROM fenor_settings WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    } catch (Throwable $e) {
        // Tabela ainda não existe — fallback para .env
        return fenorEnv()[$key] ?? $default;
    }
}

function fenorSettings(): array {
    try {
        $rows = fenorDB()->query('SELECT key, value FROM fenor_settings')->fetchAll();
        return array_column($rows, 'value', 'key');
    } catch (Throwable $e) {
        return fenorEnv();
    }
}

function saveSetting(string $key, string $value): void {
    $db     = fenorDB();
    $driver = fenorEnv()['DB_DRIVER'] ?? 'pgsql';

    if ($driver === 'mysql') {
        $db->prepare('INSERT INTO fenor_settings (key, value) VALUES (?, ?)
                      ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()')
           ->execute([$key, $value]);
    } else {
        $db->prepare('INSERT INTO fenor_settings (key, value)
                      VALUES (?, ?)
                      ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()')
           ->execute([$key, $value]);
    }
}
