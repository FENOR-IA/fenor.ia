<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// Load .env
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

// Public routes (no auth required)
$publicRoutes = ['/login', '/login/submit'];

if (!Session::isLoggedIn() && !in_array($uri, $publicRoutes)) {
    header('Location: /login');
    exit;
}

// Router
$m = [];
if ($uri === '/login' && $method === 'GET') {
    (new AuthController())->login();
} elseif ($uri === '/login/submit' && $method === 'POST') {
    (new AuthController())->loginPost();
} elseif ($uri === '/logout') {
    (new AuthController())->logout();

} elseif ($uri === '/' || $uri === '/dashboard') {
    (new DashboardController())->index();

} elseif ($uri === '/customers' && $method === 'GET') {
    (new CustomersController())->index();
} elseif ($uri === '/customers/new' && $method === 'GET') {
    (new CustomersController())->create();
} elseif ($uri === '/customers' && $method === 'POST') {
    (new CustomersController())->store();
} elseif (preg_match('#^/customers/(\d+)/edit$#', $uri, $m) && $method === 'GET') {
    (new CustomersController())->edit((int) $m[1]);
} elseif (preg_match('#^/customers/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
    (new CustomersController())->delete((int) $m[1]);
} elseif (preg_match('#^/customers/(\d+)$#', $uri, $m) && $method === 'GET') {
    (new CustomersController())->show((int) $m[1]);
} elseif (preg_match('#^/customers/(\d+)$#', $uri, $m) && $method === 'POST') {
    (new CustomersController())->update((int) $m[1]);

} elseif ($uri === '/users' && $method === 'GET') {
    (new UsersController())->index();
} elseif ($uri === '/users/new' && $method === 'GET') {
    (new UsersController())->create();
} elseif ($uri === '/users' && $method === 'POST') {
    (new UsersController())->store();
} elseif (preg_match('#^/users/(\d+)/edit$#', $uri, $m) && $method === 'GET') {
    (new UsersController())->edit((int) $m[1]);
} elseif (preg_match('#^/users/(\d+)$#', $uri, $m) && $method === 'POST') {
    (new UsersController())->update((int) $m[1]);

} else {
    http_response_code(404);
    $pageTitle = 'Page not found';
    require ROOT . '/views/layout.php';
    echo '<div class="empty"><i class="bi bi-exclamation-circle"></i><div class="empty__title">404 — Page not found</div></div>';
    require ROOT . '/views/layout_fim.php';
}
