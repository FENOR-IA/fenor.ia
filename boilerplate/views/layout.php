<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'App') ?> — <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Fenor App') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php if (Session::isLoggedIn()): ?>
<?php
    $u       = Session::user();
    $initial = mb_strtoupper(mb_substr($u['name'] ?? 'U', 0, 1));
    $appName = $_ENV['APP_NAME'] ?? 'App';
?>

<!-- MOBILE HEADER -->
<header class="header mobile-only">
    <span class="header__logo"><?= htmlspecialchars($appName) ?></span>
    <div class="dropdown">
        <div class="header__avatar" data-dropdown><?= $initial ?></div>
        <div class="dropdown__menu">
            <div class="dropdown__item">
                <i class="bi bi-person"></i>
                <?= htmlspecialchars($u['name'] ?? '') ?>
            </div>
            <div class="dropdown__separator"></div>
            <form action="/logout" method="POST">
                <button type="submit" class="dropdown__item dropdown__item--danger" style="width:100%;border:none;background:none;cursor:pointer">
                    <i class="bi bi-box-arrow-right"></i> Sign out
                </button>
            </form>
        </div>
    </div>
</header>

<!-- DESKTOP SIDEBAR -->
<aside class="sidebar desktop-only">
    <div class="sidebar__logo">
        <i class="bi bi-grid-3x3-gap-fill" style="color:var(--primary)"></i>
        <?= htmlspecialchars($appName) ?>
    </div>
    <nav class="sidebar__nav">
        <a href="/" class="sidebar__link <?= ($activePage ?? '') === 'dashboard' ? 'sidebar__link--active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="/customers" class="sidebar__link <?= ($activePage ?? '') === 'customers' ? 'sidebar__link--active' : '' ?>">
            <i class="bi bi-people"></i> Customers
        </a>
        <a href="/finance" class="sidebar__link <?= ($activePage ?? '') === 'finance' ? 'sidebar__link--active' : '' ?>">
            <i class="bi bi-cash-stack"></i> Finance
        </a>
        <?php if (Session::isAdmin()): ?>
        <div class="sidebar__section">Admin</div>
        <a href="/users" class="sidebar__link <?= ($activePage ?? '') === 'users' ? 'sidebar__link--active' : '' ?>">
            <i class="bi bi-person-gear"></i> Users
        </a>
        <?php endif; ?>
    </nav>
    <div class="sidebar__footer">
        <div class="sidebar__user">
            <div class="sidebar__avatar"><?= $initial ?></div>
            <div>
                <div class="sidebar__name"><?= htmlspecialchars($u['name'] ?? '') ?></div>
                <div class="sidebar__email"><?= htmlspecialchars($u['email'] ?? '') ?></div>
            </div>
        </div>
        <form action="/logout" method="POST">
            <button type="submit" class="btn btn--ghost btn--sm" style="width:100%">
                <i class="bi bi-box-arrow-right"></i> Sign out
            </button>
        </form>
    </div>
</aside>

<!-- MOBILE BOTTOM NAV -->
<nav class="bottom-nav mobile-only">
    <a href="/" class="bottom-nav__item <?= ($activePage ?? '') === 'dashboard' ? 'bottom-nav__item--active' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Home</span>
    </a>
    <a href="/customers" class="bottom-nav__item <?= ($activePage ?? '') === 'customers' ? 'bottom-nav__item--active' : '' ?>">
        <i class="bi bi-people"></i>
        <span>Customers</span>
    </a>
    <a href="/finance" class="bottom-nav__item <?= ($activePage ?? '') === 'finance' ? 'bottom-nav__item--active' : '' ?>">
        <i class="bi bi-cash-stack"></i>
        <span>Finance</span>
    </a>
    <?php if (Session::isAdmin()): ?>
    <a href="/users" class="bottom-nav__item <?= ($activePage ?? '') === 'users' ? 'bottom-nav__item--active' : '' ?>">
        <i class="bi bi-person-gear"></i>
        <span>Users</span>
    </a>
    <?php endif; ?>
</nav>

<?php endif; ?>

<main class="content">
