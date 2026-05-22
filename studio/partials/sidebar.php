<?php
// Detecta a página atual para marcar o item ativo
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
    <a href="<?= $baseUrl ?? '' ?>banco.php" target="_blank" rel="noopener" class="<?= $active('banco') ?>">
      <i data-lucide="database" style="width:16px;height:16px;"></i> Banco de dados
    </a>
    <a href="<?= $baseUrl ?? '' ?>settings.php" class="<?= $active('settings') ?>">
      <i data-lucide="settings" style="width:16px;height:16px;"></i> Configurações
    </a>
  </nav>
  <div class="bottom"><?= $domain ?></div>
</aside>
