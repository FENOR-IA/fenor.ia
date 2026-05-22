<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';

$config  = require __DIR__ . '/config/config.php';
$appName = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_GET['app'] ?? '')));

if (!$appName) { header('Location: dashboard.php'); exit; }

// Dados do banco
$meta = [];
try {
    $stmt = fenorDB()->prepare('SELECT * FROM fenor_apps WHERE name = ?');
    $stmt->execute([$appName]);
    $meta = $stmt->fetch() ?: [];
} catch (Throwable $e) { $meta = []; }

// Dados do filesystem
$env      = fenorEnv();
$appsPath = $env['APPS_PATH'] ?? '/var/www';
$appPath  = "$appsPath/dev/$appName";
$appEnv   = [];

$envFile = "$appPath/.env";
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strncmp(trim($line), '#', 1) === 0) continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $appEnv[trim($k)] = trim($v);
    }
}

$termUrl  = $appEnv['TERMINAL_URL'] ?? '';
$devUrl   = $appEnv['APP_URL']      ?? '';
$schema   = $appEnv['DB_SCHEMA']    ?? '';
$desc     = $meta['description']    ?? '';
$notes    = $meta['memory_notes']   ?? '';

// Lê CLAUDE.md se existir
$claudeMd = '';
$claudeFile = "$appPath/CLAUDE.md";
if (file_exists($claudeFile)) {
    $claudeMd = file_get_contents($claudeFile);
    // Extrai só as seções relevantes (sem dados técnicos sensíveis)
    if (preg_match('/## Regras\n(.*?)(?=\n##|\z)/s', $claudeMd, $m)) {
        $regras = trim($m[1]);
    }
}
$regras = $regras ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Workspace</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/fenor-ia-favicon-terracota.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
  <link rel="stylesheet" href="assets/css/studio.css">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <style>
    body { overflow: hidden; }
    .workspace {
      display: grid;
      grid-template-columns: 300px 1fr;
      grid-template-rows: 48px 1fr;
      height: 100vh;
    }
    .ws-header {
      grid-column: 1 / -1;
      display: flex; align-items: center; gap: .75rem;
      padding: 0 1.25rem;
      background: #fff;
      border-bottom: 1px solid var(--rule);
      font-size: .875rem;
    }
    .ws-header strong { font-size: 1rem; }
    .ws-header .sep { color: var(--rule); }
    .ws-header .spacer { flex: 1; }
    .ws-sidebar {
      background: var(--cream);
      border-right: 1px solid var(--rule);
      overflow-y: auto;
      padding: 1.25rem;
    }
    .ws-terminal iframe {
      width: 100%; height: 100%;
      border: none; display: block;
    }
    .ws-terminal .no-term {
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      height: 100%; color: var(--muted); gap: 1rem;
    }

    /* Instruções */
    .instr-section { margin-bottom: 1.5rem; }
    .instr-section h3 {
      font-size: .7rem; font-weight: 700; letter-spacing: .08em;
      text-transform: uppercase; color: var(--muted);
      margin-bottom: .75rem;
    }
    .cmd-block {
      background: var(--ink); color: #c9d1d9;
      border-radius: 8px; padding: .75rem 1rem;
      font-family: 'Geist Mono', monospace; font-size: .8rem;
      margin-bottom: .5rem; cursor: pointer;
      transition: opacity .15s;
      display: flex; align-items: center; justify-content: space-between; gap: .5rem;
    }
    .cmd-block:hover { opacity: .85; }
    .cmd-block .copy-hint {
      font-size: .65rem; color: #6e7681; white-space: nowrap;
    }
    .cmd-block.copied { background: #0d1117; }
    .info-item {
      font-size: .8rem; color: var(--ink);
      padding: .35rem 0; border-bottom: 1px solid var(--rule);
      display: flex; gap: .5rem;
    }
    .info-item .lbl { color: var(--muted); min-width: 60px; }
    .info-item .val { font-family: 'Geist Mono', monospace; font-size: .75rem; word-break: break-all; }
    .notes-text {
      font-size: .8rem; color: var(--ink); line-height: 1.6;
      white-space: pre-wrap;
    }
  </style>
</head>
<body>
<div class="workspace">

  <!-- Header -->
  <div class="ws-header">
    <a href="dashboard.php" style="color:var(--muted);display:flex;align-items:center;gap:.3rem;text-decoration:none;">
      <i data-lucide="arrow-left" style="width:15px;height:15px;"></i>
    </a>
    <span class="sep">|</span>
    <i data-lucide="box" style="width:15px;height:15px;color:var(--warm);"></i>
    <strong><?= htmlspecialchars($appName) ?></strong>
    <?php if ($desc): ?>
      <span style="color:var(--muted);font-size:.8rem;"><?= htmlspecialchars($desc) ?></span>
    <?php endif; ?>
    <span class="spacer"></span>
    <?php if ($devUrl): ?>
    <a href="<?= htmlspecialchars($devUrl) ?>" target="_blank"
       class="btn btn-secondary btn-xs" style="display:inline-flex;align-items:center;gap:.3rem;">
      <i data-lucide="external-link" style="width:13px;height:13px;"></i> Abrir app
    </a>
    <?php endif; ?>
  </div>

  <!-- Sidebar com instruções -->
  <div class="ws-sidebar">

    <div class="instr-section">
      <h3><i data-lucide="play-circle" style="width:11px;height:11px;display:inline;"></i> Como começar</h3>
      <p style="font-size:.8rem;color:var(--muted);margin-bottom:.75rem;">
        Digite o comando abaixo no terminal para iniciar o Claude Code neste app:
      </p>
      <div class="cmd-block" onclick="copyCmd(this, 'claude')">
        <span>$ claude</span>
        <span class="copy-hint">clique para copiar</span>
      </div>
    </div>

    <div class="instr-section">
      <h3><i data-lucide="git-branch" style="width:11px;height:11px;display:inline;"></i> Git</h3>
      <div class="cmd-block" onclick="copyCmd(this, 'git status')">
        <span>$ git status</span>
        <span class="copy-hint">ver alterações</span>
      </div>
      <div class="cmd-block" onclick="copyCmd(this, 'git add . && git commit -m \"\"')">
        <span>$ git add . && git commit</span>
        <span class="copy-hint">salvar versão</span>
      </div>
      <div class="cmd-block" onclick="copyCmd(this, 'git push origin dev')">
        <span>$ git push origin dev</span>
        <span class="copy-hint">enviar para GitHub</span>
      </div>
    </div>

    <div class="instr-section">
      <h3><i data-lucide="database" style="width:11px;height:11px;display:inline;"></i> Banco de dados</h3>
      <?php if ($schema): ?>
      <div class="info-item">
        <span class="lbl">Schema</span>
        <span class="val"><?= htmlspecialchars($schema) ?></span>
      </div>
      <?php endif; ?>
      <div class="info-item" style="border:none;padding-top:.5rem;">
        <a href="banco.php?app=<?= urlencode($appName) ?>" target="_blank"
           style="display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .85rem;background:#1d4ed8;color:#fff;border-radius:7px;font-size:.8rem;font-weight:600;text-decoration:none;transition:filter .15s;"
           onmouseover="this.style.filter='brightness(.85)'" onmouseout="this.style.filter=''">
          <i data-lucide="database" style="width:14px;height:14px;"></i>
          Abrir banco de dados
        </a>
      </div>
    </div>

    <div class="instr-section">
      <h3><i data-lucide="folder" style="width:11px;height:11px;display:inline;"></i> Informações</h3>
      <?php if ($devUrl): ?>
      <div class="info-item">
        <span class="lbl">URL</span>
        <a href="<?= htmlspecialchars($devUrl) ?>" target="_blank" class="val" style="color:var(--warm);"><?= htmlspecialchars($devUrl) ?></a>
      </div>
      <?php endif; ?>
      <div class="info-item">
        <span class="lbl">Pasta</span>
        <span class="val"><?= htmlspecialchars($appPath) ?></span>
      </div>
    </div>

    <?php if ($notes): ?>
    <div class="instr-section">
      <h3><i data-lucide="sticky-note" style="width:11px;height:11px;display:inline;"></i> Notas do projeto</h3>
      <div class="notes-text"><?= htmlspecialchars($notes) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($regras): ?>
    <div class="instr-section">
      <h3><i data-lucide="shield-check" style="width:11px;height:11px;display:inline;"></i> Regras</h3>
      <div class="notes-text"><?= htmlspecialchars($regras) ?></div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Terminal -->
  <div class="ws-terminal">
    <?php if ($termUrl): ?>
      <iframe src="<?= htmlspecialchars($termUrl) ?>"
              allow="clipboard-read; clipboard-write"
              title="Terminal — <?= htmlspecialchars($appName) ?>"></iframe>
    <?php else: ?>
      <div class="no-term">
        <i data-lucide="terminal" style="width:48px;height:48px;color:var(--rule);"></i>
        <p>Terminal não disponível.<br>Provisione o app primeiro.</p>
        <a href="dashboard.php" class="btn btn-primary">← Voltar</a>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
lucide.createIcons();

function copyCmd(el, cmd) {
  const hint = el.querySelector('.copy-hint');
  const original = hint.textContent;

  function done() {
    hint.textContent = '✓ copiado!';
    el.classList.add('copied');
    setTimeout(() => { hint.textContent = original; el.classList.remove('copied'); }, 1500);
  }

  if (navigator.clipboard) {
    navigator.clipboard.writeText(cmd).then(done);
  } else {
    const ta = Object.assign(document.createElement('textarea'), {value: cmd, style: 'position:fixed;opacity:0'});
    document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
    done();
  }
}
</script>
</body>
</html>
