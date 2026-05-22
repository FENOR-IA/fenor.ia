<?php
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$config = require __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Terminal — Fenor Studio</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/fenor-ia-favicon-terracota.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="assets/css/studio.css">
  <style>
    .terminal-full iframe { height: calc(100vh - 56px); }
  </style>
</head>
<body>
<div class="layout">
  <?php $pageTitle = 'Terminal'; include __DIR__ . '/partials/sidebar.php'; ?>

  <div class="main">
    <?php include __DIR__ . '/partials/topbar.php'; ?>
    <?php if ($config['terminal_url']): ?>
    <div class="terminal-embed terminal-full">
      <iframe src="<?= htmlspecialchars($config['terminal_url']) ?>"
              title="Terminal Fenor"
              allow="clipboard-read; clipboard-write">
      </iframe>
    </div>
    <?php else: ?>
    <div class="content" style="text-align:center;padding:4rem;color:var(--muted);">
      <p>Terminal URL não configurado.</p>
      <p style="margin-top:.5rem;font-size:.85rem;">Configure TERMINAL_URL em /etc/fenor/.env</p>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
