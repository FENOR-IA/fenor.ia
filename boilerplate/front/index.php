<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// Carrega .env
foreach (file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
    if (str_starts_with(trim($linha), '#') || !str_contains($linha, '=')) continue;
    [$chave, $valor] = explode('=', $linha, 2);
    $_ENV[trim($chave)] = trim($valor);
}

// Autoload simples
spl_autoload_register(function (string $classe) {
    $dirs = [ROOT . '/src/Config', ROOT . '/src/Auth', ROOT . '/src/Controllers'];
    foreach ($dirs as $dir) {
        $arquivo = "$dir/$classe.php";
        if (file_exists($arquivo)) { require_once $arquivo; return; }
    }
});

Session::iniciar();

$metodo = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/') ?: '/';

// Rotas públicas
$publicas = ['/login', '/login/entrar'];

if (!Session::estaLogado() && !in_array($uri, $publicas)) {
    header('Location: /login');
    exit;
}

// Roteador
match (true) {

    // Auth
    $uri === '/login' && $metodo === 'GET'
        => (new AuthController())->login(),
    $uri === '/login/entrar' && $metodo === 'POST'
        => (new AuthController())->loginPost(),
    $uri === '/logout'
        => (new AuthController())->logout(),

    // Dashboard
    $uri === '/' || $uri === '/dashboard'
        => (new DashboardController())->index(),

    // Clientes
    $uri === '/clientes' && $metodo === 'GET'
        => (new ClientesController())->index(),
    $uri === '/clientes/novo' && $metodo === 'GET'
        => (new ClientesController())->novo(),
    $uri === '/clientes' && $metodo === 'POST'
        => (new ClientesController())->salvar(),
    preg_match('#^/clientes/(\d+)$#', $uri, $m) && $metodo === 'GET'
        => (new ClientesController())->ver((int) $m[1]),
    preg_match('#^/clientes/(\d+)/editar$#', $uri, $m) && $metodo === 'GET'
        => (new ClientesController())->editar((int) $m[1]),
    preg_match('#^/clientes/(\d+)$#', $uri, $m) && $metodo === 'POST'
        => (new ClientesController())->atualizar((int) $m[1]),
    preg_match('#^/clientes/(\d+)/excluir$#', $uri, $m) && $metodo === 'POST'
        => (new ClientesController())->excluir((int) $m[1]),

    // Usuários (admin)
    $uri === '/usuarios' && $metodo === 'GET'
        => (new UsuariosController())->index(),
    $uri === '/usuarios/novo' && $metodo === 'GET'
        => (new UsuariosController())->novo(),
    $uri === '/usuarios' && $metodo === 'POST'
        => (new UsuariosController())->salvar(),
    preg_match('#^/usuarios/(\d+)/editar$#', $uri, $m) && $metodo === 'GET'
        => (new UsuariosController())->editar((int) $m[1]),
    preg_match('#^/usuarios/(\d+)$#', $uri, $m) && $metodo === 'POST'
        => (new UsuariosController())->atualizar((int) $m[1]),

    // 404
    default => (function () {
        http_response_code(404);
        $tituloPagina = 'Página não encontrada';
        require ROOT . '/views/layout.php';
        echo '<div class="vazio"><i class="bi bi-exclamation-circle"></i><div class="vazio__titulo">404 — Página não encontrada</div></div>';
        require ROOT . '/views/layout_fim.php';
    })()
};
