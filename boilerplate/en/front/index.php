<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// Load environment variables
foreach (file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

// Simple autoloader
spl_autoload_register(function (string $class) {
    $dirs = [ROOT . '/src/Config', ROOT . '/src/Auth', ROOT . '/src/Controllers'];
    foreach ($dirs as $dir) {
        $file = "$dir/$class.php";
        if (file_exists($file)) { require_once $file; return; }
    }
});

Session::start();

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/') ?: '/';

// Public routes (no authentication required)
$publicRoutes = ['/login', '/login/submit'];

if (!Session::isLoggedIn() && !in_array($uri, $publicRoutes)) {
    header('Location: /login');
    exit;
}

// ─── Router ───────────────────────────────────────────────────────────────────
$m = [];

// Auth
if ($uri === '/login' && $method === 'GET') {
    (new AuthController())->login();
} elseif ($uri === '/login/submit' && $method === 'POST') {
    (new AuthController())->loginPost();
} elseif ($uri === '/logout') {
    (new AuthController())->logout();

// Dashboard
} elseif ($uri === '/' || $uri === '/dashboard') {
    (new DashboardController())->index();

// ─── TODO: add your routes here ──────────────────────────────────────────────
// Example:
//   } elseif ($uri === '/contacts' && $method === 'GET') {
//       (new ContactsController())->index();

} else {
    http_response_code(404);
    $pageTitle = 'Page not found';
    require ROOT . '/views/layout.php';
    echo '<div class="empty"><i class="bi bi-exclamation-circle"></i><div class="empty__title">404 — Page not found</div></div>';
    require ROOT . '/views/layout_fim.php';
}
