<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? 'App') ?> — <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Fenor App') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php if (Session::estaLogado()): ?>
<?php
    $u      = Session::usuario();
    $inicial = mb_strtoupper(mb_substr($u['nome'] ?? 'U', 0, 1));
    $appNome = $_ENV['APP_NAME'] ?? 'App';
?>

<!-- HEADER MOBILE -->
<header class="header mobile-only">
    <span class="header__logo"><?= htmlspecialchars($appNome) ?></span>
    <div class="dropdown">
        <div class="header__avatar" data-dropdown><?= $inicial ?></div>
        <div class="dropdown__menu">
            <div class="dropdown__item">
                <i class="bi bi-person"></i>
                <?= htmlspecialchars($u['nome'] ?? '') ?>
            </div>
            <div class="dropdown__separador"></div>
            <form action="/logout" method="POST">
                <button type="submit" class="dropdown__item dropdown__item--perigo" style="width:100%;border:none;background:none;cursor:pointer">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </button>
            </form>
        </div>
    </div>
</header>

<!-- SIDEBAR DESKTOP -->
<aside class="sidebar desktop-only">
    <div class="sidebar__logo">
        <i class="bi bi-grid-3x3-gap-fill" style="color:var(--primary)"></i>
        <?= htmlspecialchars($appNome) ?>
    </div>
    <nav class="sidebar__nav">
        <a href="/" class="sidebar__link <?= ($paginaAtiva ?? '') === 'dashboard' ? 'sidebar__link--ativo' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="/clientes" class="sidebar__link <?= ($paginaAtiva ?? '') === 'clientes' ? 'sidebar__link--ativo' : '' ?>">
            <i class="bi bi-people"></i> Clientes
        </a>
        <a href="/financeiro" class="sidebar__link <?= ($paginaAtiva ?? '') === 'financeiro' ? 'sidebar__link--ativo' : '' ?>">
            <i class="bi bi-cash-stack"></i> Financeiro
        </a>
        <?php if (Session::isAdmin()): ?>
        <div class="sidebar__secao">Admin</div>
        <a href="/usuarios" class="sidebar__link <?= ($paginaAtiva ?? '') === 'usuarios' ? 'sidebar__link--ativo' : '' ?>">
            <i class="bi bi-person-gear"></i> Usuários
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar__footer">
        <div class="sidebar__usuario">
            <div class="sidebar__avatar"><?= $inicial ?></div>
            <div>
                <div class="sidebar__nome"><?= htmlspecialchars($u['nome'] ?? '') ?></div>
                <div class="sidebar__email"><?= htmlspecialchars($u['email'] ?? '') ?></div>
            </div>
        </div>
        <form action="/logout" method="POST">
            <button type="submit" class="btn btn--ghost btn--sm" style="width:100%">
                <i class="bi bi-box-arrow-right"></i> Sair
            </button>
        </form>
    </div>
</aside>

<!-- NAV INFERIOR MOBILE -->
<nav class="nav-inferior mobile-only">
    <a href="/" class="nav-inferior__item <?= ($paginaAtiva ?? '') === 'dashboard' ? 'nav-inferior__item--ativo' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Início</span>
    </a>
    <a href="/clientes" class="nav-inferior__item <?= ($paginaAtiva ?? '') === 'clientes' ? 'nav-inferior__item--ativo' : '' ?>">
        <i class="bi bi-people"></i>
        <span>Clientes</span>
    </a>
    <a href="/financeiro" class="nav-inferior__item <?= ($paginaAtiva ?? '') === 'financeiro' ? 'nav-inferior__item--ativo' : '' ?>">
        <i class="bi bi-cash-stack"></i>
        <span>Financeiro</span>
    </a>
    <?php if (Session::isAdmin()): ?>
    <a href="/usuarios" class="nav-inferior__item <?= ($paginaAtiva ?? '') === 'usuarios' ? 'nav-inferior__item--ativo' : '' ?>">
        <i class="bi bi-person-gear"></i>
        <span>Usuários</span>
    </a>
    <?php endif; ?>
</nav>

<?php endif; ?>

<main class="conteudo">
