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
    // App context: connect with the app's isolated credentials
    // The app user only has access to its own schema (restricted search_path)
    $dbHost   = $appEnv['DB_HOST'];
    $dbPort   = $appEnv['DB_PORT']   ?? '5432';
    $dbUser   = $appEnv['DB_USER']   ?? '';
    $dbPass   = $appEnv['DB_PASS']   ?? '';
    $dbName   = $appEnv['DB_NAME']   ?? 'fenor';
    $dbDriver = $appEnv['DB_DRIVER'] ?? $globalEnv['DB_DRIVER'] ?? 'pgsql';
} else {
    // General context (sidebar): fenor_apps_viewer — sees all app schemas,
    // but has NO access to the public schema (fenor_settings, fenor_apps)
    $dbHost   = $globalEnv['DB_HOST'] ?? '127.0.0.1';
    $dbPort   = $globalEnv['DB_PORT'] ?? '5432';
    $dbUser   = 'fenor_apps_viewer';
    $dbPass   = $globalEnv['DB_APPS_VIEWER_PASS'] ?? '';
    $dbName   = 'fenor';
    $dbDriver = $globalEnv['DB_DRIVER'] ?? 'pgsql';
}

$server = "$dbHost:$dbPort";

// Map driver to the name used by Adminer 5.x
$adminerDriver = $dbDriver === 'mysql' ? 'server' : 'pgsql';

// Inject credentials as if it were a login POST
$_POST['auth'] = [
    'driver'    => $adminerDriver,
    'server'    => $server,
    'username'  => $dbUser,
    'password'  => $dbPass,
    'db'        => $dbName,
    'permanent' => '1',
];
$_SERVER['REQUEST_METHOD'] = 'POST';

// CSRF token used by Adminer — inject a dummy to avoid blocking
$_POST['token'] = '';

include __DIR__ . '/adminer.php';
