<?php

class Session
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $nome = $_ENV['APP_NAME'] ?? 'fenor_app';
            session_name(preg_replace('/[^a-zA-Z0-9_]/', '_', $nome) . '_session');
            session_set_cookie_params([
                'lifetime' => 604800,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function set(string $chave, mixed $valor): void
    {
        $_SESSION[$chave] = $valor;
    }

    public static function get(string $chave, mixed $padrao = null): mixed
    {
        return $_SESSION[$chave] ?? $padrao;
    }

    public static function estaLogado(): bool
    {
        return !empty($_SESSION['usuario_id']);
    }

    public static function usuario(): array
    {
        return $_SESSION['usuario'] ?? [];
    }

    public static function usuarioId(): ?int
    {
        return $_SESSION['usuario_id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['usuario']['role'] ?? '') === 'admin';
    }

    public static function flash(string $tipo, string $mensagem): void
    {
        $_SESSION['_flash'][$tipo] = $mensagem;
    }

    public static function getFlash(string $tipo): ?string
    {
        $msg = $_SESSION['_flash'][$tipo] ?? null;
        unset($_SESSION['_flash'][$tipo]);
        return $msg;
    }

    public static function destruir(): void
    {
        session_unset();
        session_destroy();
    }
}
