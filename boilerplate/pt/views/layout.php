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

<?php if (Session::isLoggedIn()): ?>
<?php
    $u       = Session::user();
    $inicial = mb_strtoupper(mb_substr($u['name'] ?? 'U', 0, 1));
    $nomeApp = $_ENV['APP_NAME'] ?? 'App';
?>

<!-- CABEÇALHO MOBILE -->
<header class="header mobile-only">
    <span class="header__logo"><?= htmlspecialchars($nomeApp) ?></span>
    <div class="dropdown">
        <div class="header__avatar" data-dropdown><?= $inicial ?></div>
        <div class="dropdown__menu">
            <div class="dropdown__item">
                <i class="bi bi-person"></i>
                <?= htmlspecialchars($u['name'] ?? '') ?>
            </div>
            <div class="dropdown__separator"></div>
            <form action="/sair" method="POST">
                <button type="submit" class="dropdown__item dropdown__item--danger" style="width:100%;border:none;background:none;cursor:pointer">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </button>
            </form>
        </div>
    </div>
</header>

<!-- MENU LATERAL DESKTOP -->
<aside class="sidebar desktop-only">
    <div class="sidebar__logo">
        <i class="bi bi-grid-3x3-gap-fill" style="color:var(--primary)"></i>
        <?= htmlspecialchars($nomeApp) ?>
    </div>
    <nav class="sidebar__nav">
        <a href="/" class="sidebar__link <?= ($paginaAtiva ?? '') === 'painel' ? 'sidebar__link--active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Painel
        </a>
        <a href="/clientes" class="sidebar__link <?= ($paginaAtiva ?? '') === 'clientes' ? 'sidebar__link--active' : '' ?>">
            <i class="bi bi-people"></i> Clientes
        </a>
        <a href="/financeiro" class="sidebar__link <?= ($paginaAtiva ?? '') === 'financeiro' ? 'sidebar__link--active' : '' ?>">
            <i class="bi bi-cash-stack"></i> Financeiro
        </a>
        <?php if (Session::isAdmin()): ?>
        <div class="sidebar__section">Administração</div>
        <a href="/usuarios" class="sidebar__link <?= ($paginaAtiva ?? '') === 'usuarios' ? 'sidebar__link--active' : '' ?>">
            <i class="bi bi-person-gear"></i> Usuários
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar__footer">
        <div class="sidebar__user">
            <div class="sidebar__avatar"><?= $inicial ?></div>
            <div>
                <div class="sidebar__name"><?= htmlspecialchars($u['name'] ?? '') ?></div>
                <div class="sidebar__email"><?= htmlspecialchars($u['email'] ?? '') ?></div>
            </div>
        </div>
        <form action="/sair" method="POST">
            <button type="submit" class="btn btn--ghost btn--sm" style="width:100%">
                <i class="bi bi-box-arrow-right"></i> Sair
            </button>
        </form>
    </div>
</aside>

<!-- MENU INFERIOR MOBILE -->
<nav class="bottom-nav mobile-only">
    <a href="/" class="bottom-nav__item <?= ($paginaAtiva ?? '') === 'painel' ? 'bottom-nav__item--active' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Painel</span>
    </a>
    <a href="/clientes" class="bottom-nav__item <?= ($paginaAtiva ?? '') === 'clientes' ? 'bottom-nav__item--active' : '' ?>">
        <i class="bi bi-people"></i>
        <span>Clientes</span>
    </a>
    <a href="/financeiro" class="bottom-nav__item <?= ($paginaAtiva ?? '') === 'financeiro' ? 'bottom-nav__item--active' : '' ?>">
        <i class="bi bi-cash-stack"></i>
        <span>Financeiro</span>
    </a>
    <?php if (Session::isAdmin()): ?>
    <a href="/usuarios" class="bottom-nav__item <?= ($paginaAtiva ?? '') === 'usuarios' ? 'bottom-nav__item--active' : '' ?>">
        <i class="bi bi-person-gear"></i>
        <span>Usuários</span>
    </a>
    <?php endif; ?>
</nav>

<?php endif; ?>

<main class="content">
