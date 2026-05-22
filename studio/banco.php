<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/config/db.php';

$appName = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['app'] ?? '')));
$appEnv  = [];

if ($appName) {
    $globalEnv = fenorEnv();
    $appsPath  = $globalEnv['APPS_PATH'] ?? '/var/www';
    $envFile   = "$appsPath/dev/$appName/.env";
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strncmp(trim($line), '#', 1) === 0) continue;
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            $appEnv[trim($k)] = trim($v);
        }
    }
}

$globalEnv = fenorEnv();

if (!empty($appEnv['DB_HOST'])) {
    // Contexto de app: conecta com as credenciais isoladas do app
    // O usuário do app só tem acesso ao seu próprio schema (search_path restrito)
    $dbHost   = $appEnv['DB_HOST'];
    $dbPort   = $appEnv['DB_PORT']   ?? '5432';
    $dbUser   = $appEnv['DB_USER']   ?? '';
    $dbPass   = $appEnv['DB_PASS']   ?? '';
    $dbName   = $appEnv['DB_NAME']   ?? 'fenor';
    $dbDriver = $appEnv['DB_DRIVER'] ?? $globalEnv['DB_DRIVER'] ?? 'pgsql';
} else {
    // Contexto geral (sidebar): fenor_apps_viewer — vê todos os schemas de apps,
    // mas NÃO tem acesso ao schema public (fenor_settings, fenor_apps)
    $dbHost   = $globalEnv['DB_HOST'] ?? '127.0.0.1';
    $dbPort   = $globalEnv['DB_PORT'] ?? '5432';
    $dbUser   = 'fenor_apps_viewer';
    $dbPass   = $globalEnv['DB_APPS_VIEWER_PASS'] ?? '';
    $dbName   = 'fenor';
    $dbDriver = $globalEnv['DB_DRIVER'] ?? 'pgsql';
}

$server = "$dbHost:$dbPort";

// Mapeia driver para o nome que o Adminer 5.x usa
$adminerDriver = $dbDriver === 'mysql' ? 'server' : 'pgsql';

// Injeta as credenciais como se fosse um POST de login
$_POST['auth'] = [
    'driver'    => $adminerDriver,
    'server'    => $server,
    'username'  => $dbUser,
    'password'  => $dbPass,
    'db'        => $dbName,
    'permanent' => '1',
];
$_SERVER['REQUEST_METHOD'] = 'POST';

// Token CSRF que o Adminer usa — injeta um dummy para não bloquear
$_POST['token'] = '';

include __DIR__ . '/adminer.php';
