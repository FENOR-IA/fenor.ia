<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

$config = require __DIR__ . '/config/config.php';

$templatesDir  = '/etc/fenor/templates';
$indexFile     = $templatesDir . '/index.json';
$templates     = [];
$lastUpdate    = null;

if (file_exists($indexFile)) {
    $templates  = json_decode(file_get_contents($indexFile), true) ?: [];
    $lastUpdate = date('d/m/Y H:i', filemtime($indexFile));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Templates — Fenor Studio</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/fenor-ia-favicon-terracota.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
  <link rel="stylesheet" href="assets/css/studio.css">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body>
<div class="layout">
  <?php $pageTitle = 'Templates'; include __DIR__ . '/partials/sidebar.php'; ?>
  <div class="main">
    <?php include __DIR__ . '/partials/topbar.php'; ?>
    <div class="content">

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
        <div>
          <h1 style="font-size:1.1rem;font-weight:600;margin:0 0 .2rem;">Templates</h1>
          <?php if ($lastUpdate): ?>
            <p style="font-size:.78rem;color:var(--muted);margin:0;">Atualizado em <?= $lastUpdate ?></p>
          <?php endif; ?>
        </div>
      </div>

      <?php if (empty($templates)): ?>
        <div style="background:var(--cream);border-radius:12px;padding:2.5rem;text-align:center;">
          <i data-lucide="layers" style="width:32px;height:32px;stroke:var(--muted);margin-bottom:1rem;"></i>
          <p style="color:var(--muted);margin-bottom:1.25rem;">Nenhum template instalado.</p>
          <div style="background:var(--ink);color:#c9d1d9;padding:1rem 1.25rem;border-radius:8px;font-family:'Geist Mono',monospace;font-size:.82rem;text-align:left;display:inline-block;">
            fenor templates update
          </div>
        </div>
      <?php else: ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;margin-bottom:2rem;">
          <?php foreach ($templates as $tpl): ?>
          <div style="border:1px solid var(--rule);border-radius:12px;padding:1.25rem;background:#fff;">
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem;">
              <i data-lucide="layers" style="width:18px;height:18px;stroke:var(--warm);flex-shrink:0;"></i>
              <strong style="font-size:.95rem;"><?= htmlspecialchars($tpl['label'] ?? $tpl['name']) ?></strong>
              <span style="font-size:.65rem;padding:.1rem .45rem;border-radius:4px;background:var(--cream);color:var(--muted);font-weight:600;letter-spacing:.04em;margin-left:auto;">
                v<?= htmlspecialchars($tpl['version'] ?? '—') ?>
              </span>
            </div>
            <p style="font-size:.8rem;color:var(--muted);line-height:1.6;margin:0 0 .75rem;">
              <?= htmlspecialchars($tpl['description'] ?? '') ?>
            </p>
            <div style="font-family:'Geist Mono',monospace;font-size:.75rem;color:var(--warm);background:#fff8f5;padding:.3rem .6rem;border-radius:5px;display:inline-block;">
              <?= htmlspecialchars($tpl['name']) ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      <?php endif; ?>

      <!-- Terminal instruction -->
      <div style="border:1px solid var(--rule);border-radius:12px;padding:1.25rem;">
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.875rem;">
          <i data-lucide="terminal" style="width:16px;height:16px;stroke:var(--muted);"></i>
          <span style="font-size:.8rem;font-weight:600;color:var(--ink);">Gerenciar via terminal</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:.6rem;">
          <div>
            <p style="font-size:.75rem;color:var(--muted);margin:0 0 .3rem;">Atualizar / instalar novos templates:</p>
            <div style="background:var(--ink);color:#c9d1d9;padding:.65rem 1rem;border-radius:7px;font-family:'Geist Mono',monospace;font-size:.8rem;">
              fenor templates update
            </div>
          </div>
          <div>
            <p style="font-size:.75rem;color:var(--muted);margin:0 0 .3rem;">Listar templates instalados:</p>
            <div style="background:var(--ink);color:#c9d1d9;padding:.65rem 1rem;border-radius:7px;font-family:'Geist Mono',monospace;font-size:.8rem;">
              fenor templates list
            </div>
          </div>
        </div>
        <p style="font-size:.75rem;color:var(--muted);margin:.875rem 0 0;line-height:1.6;">
          Os templates são clonados de <strong>github.com/FENOR-IA/fenor-ia-templates</strong> e ficam em <code style="font-size:.72rem;background:var(--cream);padding:.1rem .35rem;border-radius:4px;">/etc/fenor/templates/</code>.
          Após atualizar, recarregue esta página para ver os novos templates.
        </p>
      </div>

    </div>
  </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
