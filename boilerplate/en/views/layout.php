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
            <i class="bi bi-speedometer2"></i> Home
        </a>
        <!-- TODO: add your nav items here -->
        <!-- Example:
        <a href="/contacts" class="sidebar__link <?php /* = ($activePage ?? '') === 'contacts' ? 'sidebar__link--active' : '' */ ?>">
            <i class="bi bi-people"></i> Contacts
        </a>
        -->
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
    <!-- TODO: add your mobile nav items here -->
</nav>

<?php endif; ?>

<main class="content">
