<?php

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// Carrega variáveis de ambiente
foreach (file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
    $linha = trim($linha);
    if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) continue;
    [$chave, $valor] = explode('=', $linha, 2);
    $_ENV[trim($chave)] = trim($valor);
}

// Autoloader simples
spl_autoload_register(function (string $classe) {
    $dirs = [ROOT . '/src/Config', ROOT . '/src/Auth', ROOT . '/src/Controllers'];
    foreach ($dirs as $dir) {
        $arquivo = "$dir/$classe.php";
        if (file_exists($arquivo)) { require_once $arquivo; return; }
    }
});

Session::start();

$metodo = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = rtrim($uri, '/') ?: '/';

// Rotas públicas (sem autenticação)
$rotasPublicas = ['/entrar', '/entrar/salvar'];

if (!Session::isLoggedIn() && !in_array($uri, $rotasPublicas)) {
    header('Location: /entrar');
    exit;
}

// Roteador
$m = [];
if ($uri === '/entrar' && $metodo === 'GET') {
    (new AuthController())->login();
} elseif ($uri === '/entrar/salvar' && $metodo === 'POST') {
    (new AuthController())->loginPost();
} elseif ($uri === '/sair') {
    (new AuthController())->logout();

} elseif ($uri === '/' || $uri === '/painel') {
    (new DashboardController())->index();

} elseif ($uri === '/clientes' && $metodo === 'GET') {
    (new ClientesController())->listar();
} elseif ($uri === '/clientes/novo' && $metodo === 'GET') {
    (new ClientesController())->criar();
} elseif ($uri === '/clientes' && $metodo === 'POST') {
    (new ClientesController())->salvar();
} elseif (preg_match('#^/clientes/(\d+)/editar$#', $uri, $m) && $metodo === 'GET') {
    (new ClientesController())->editar((int) $m[1]);
} elseif (preg_match('#^/clientes/(\d+)/excluir$#', $uri, $m) && $metodo === 'POST') {
    (new ClientesController())->excluir((int) $m[1]);
} elseif (preg_match('#^/clientes/(\d+)$#', $uri, $m) && $metodo === 'GET') {
    (new ClientesController())->ver((int) $m[1]);
} elseif (preg_match('#^/clientes/(\d+)$#', $uri, $m) && $metodo === 'POST') {
    (new ClientesController())->atualizar((int) $m[1]);

} elseif ($uri === '/usuarios' && $metodo === 'GET') {
    (new UsuariosController())->listar();
} elseif ($uri === '/usuarios/novo' && $metodo === 'GET') {
    (new UsuariosController())->criar();
} elseif ($uri === '/usuarios' && $metodo === 'POST') {
    (new UsuariosController())->salvar();
} elseif (preg_match('#^/usuarios/(\d+)/editar$#', $uri, $m) && $metodo === 'GET') {
    (new UsuariosController())->editar((int) $m[1]);
} elseif (preg_match('#^/usuarios/(\d+)$#', $uri, $m) && $metodo === 'POST') {
    (new UsuariosController())->atualizar((int) $m[1]);

} else {
    http_response_code(404);
    $tituloPagina = 'Página não encontrada';
    require ROOT . '/views/layout.php';
    echo '<div class="empty"><i class="bi bi-exclamation-circle"></i><div class="empty__title">404 — Página não encontrada</div></div>';
    require ROOT . '/views/layout_fim.php';
}
