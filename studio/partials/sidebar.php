<?php
// Detect current page to mark the active item
$current = basename($_SERVER['PHP_SELF'], '.php');
$active  = fn(string $page) => $current === $page ? 'active' : '';
$domain  = htmlspecialchars($config['base_domain'] ?? '');
?>
<aside class="sidebar">
  <div class="brand">
    <img src="<?= $baseUrl ?? '' ?>assets/img/fenor-ia-stacked-transparent-ink.svg" alt="Fenor">
  </div>
  <nav>
    <a href="<?= $baseUrl ?? '' ?>dashboard.php" class="<?= $active('dashboard') ?>">
      <i data-lucide="layout-grid" style="width:16px;height:16px;"></i> Apps
    </a>
    <a href="<?= $baseUrl ?? '' ?>templates.php" class="<?= $active('templates') ?>">
      <i data-lucide="layers" style="width:16px;height:16px;"></i> Templates
    </a>
    <a href="<?= $baseUrl ?? '' ?>banco.php" target="_blank" rel="noopener" class="<?= $active('banco') ?>">
      <i data-lucide="database" style="width:16px;height:16px;"></i> Database
    </a>
    <a href="<?= $baseUrl ?? '' ?>settings.php" class="<?= $active('settings') ?>">
      <i data-lucide="settings" style="width:16px;height:16px;"></i> Settings
    </a>
  </nav>
  <div class="bottom"><?= $domain ?></div>
</aside>
