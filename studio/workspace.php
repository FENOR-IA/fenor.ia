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

$termUrl    = $appEnv['TERMINAL_URL'] ?? '';
$devUrl     = $appEnv['APP_URL']      ?? '';
$schema     = $appEnv['DB_SCHEMA']    ?? '';
$desc       = $meta['description']    ?? '';
$notes      = $meta['memory_notes']   ?? '';
$adminEmail = $appEnv['ADMIN_EMAIL']  ?? '';
$adminPass  = $appEnv['ADMIN_PASS']   ?? '';
$hasGit = is_dir("$appPath/.git");

// Read remote from .git/config if not in DB
$gitRemote = trim($meta['github_repo'] ?? '');
if (!$gitRemote && $hasGit) {
    $gitCfgFile = "$appPath/.git/config";
    if (is_readable($gitCfgFile)) {
        $gitCfg = file_get_contents($gitCfgFile);
        if (preg_match('/\[remote "origin"\][^\[]*url\s*=\s*([^\n]+)/s', $gitCfg, $rm)) {
            $gitRemote = trim($rm[1]);
        }
    }
}
$hasGithub = !empty($gitRemote); // remote configured (DB or .git/config)

// Check if the deploy key exists on disk
$appSafe       = str_replace('-', '_', $appName);
$deployKeyFile = "/etc/fenor/keys/{$appSafe}.pub";
$hasDeployKey  = file_exists($deployKeyFile);

// Studio GitHub settings
$studioSettings = fenorSettings();
$hasGithubToken = !empty(trim($studioSettings['GITHUB_TOKEN'] ?? ''));
$authMethod     = $studioSettings['GITHUB_AUTH_METHOD'] ?? '';
$githubUser     = $studioSettings['GITHUB_USER']        ?? '';
$githubOrg      = $studioSettings['GITHUB_ORG']         ?? '';
$ghOwner        = $githubOrg ?: $githubUser;
// PAT uses HTTPS — no SSH deploy key needed
$isPatAuth      = $hasGithubToken && $authMethod === 'pat';
// Can push/pull: either has a deploy key (SSH) or uses PAT (HTTPS)
$canPushPull    = $isPatAuth || $hasDeployKey;

// Detect invalid/bare remote: "owner/repo" with no URL scheme — left by old newapp bug
$remoteIsBare = $gitRemote
    && strpos($gitRemote, '://') === false
    && strpos($gitRemote, '@')   === false
    && strpos($gitRemote, '/')   !== false;

// Auto-fix bare remote at page load when PAT is configured
if ($remoteIsBare && $isPatAuth) {
    $bareRepo = rtrim($gitRemote, '.git');
    $fixedUrl = 'https://' . trim($studioSettings['GITHUB_TOKEN']) . "@github.com/{$bareRepo}.git";
    $fixCmd   = 'sudo /usr/local/bin/fenor-git ' . escapeshellarg($appName) . ' set-remote ' . escapeshellarg($fixedUrl) . ' 2>&1';
    @shell_exec($fixCmd);
    $gitRemote = "https://github.com/{$bareRepo}"; // clean URL for display
}

// Convert SSH alias / HTTPS remote to a browser-friendly GitHub URL
// Handles: git@github-{app}:owner/repo.git  git@github.com:owner/repo.git  https://github.com/owner/repo
$githubBrowserUrl = '';
if ($gitRemote && preg_match('/github[^:]*:([^\/\s]+\/[^\s]+?)(?:\.git)?$/i', $gitRemote, $rm2)) {
    $githubBrowserUrl = 'https://github.com/' . $rm2[1];
} elseif ($gitRemote && preg_match('#https?://github\.com/([^\s]+?)(?:\.git)?$#i', $gitRemote, $rm2)) {
    $githubBrowserUrl = 'https://github.com/' . $rm2[1];
}

// Lê CLAUDE.md se existir
$claudeMd = '';
$claudeFile = "$appPath/CLAUDE.md";
if (file_exists($claudeFile)) {
    $claudeMd = file_get_contents($claudeFile);
    // Extrai só as seções relevantes (sem dados técnicos sensíveis)
    if (preg_match('/## Rules\n(.*?)(?=\n##|\z)/s', $claudeMd, $m)) {
        $rules = trim($m[1]);
    }
}
$rules = $rules ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Workspace</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/fenor-ia-favicon-terracota.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
  <link rel="stylesheet" href="assets/css/studio.css">
  <script src="assets/js/studio.js"></script>
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

    /* Git modals */
    .git-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.45);
      display: flex; align-items: center; justify-content: center;
      z-index: 1000;
    }
    .git-modal-box {
      background: #fff; border-radius: 14px; padding: 1.75rem;
      width: min(460px, 92vw); max-height: 80vh; overflow-y: auto;
      box-shadow: 0 8px 32px rgba(0,0,0,.18);
    }
    .git-modal-box h3 {
      font-size: 1rem; font-weight: 700; color: var(--ink); margin: 0 0 .35rem;
    }
    .git-modal-box p {
      font-size: .82rem; color: var(--muted); line-height: 1.6; margin-bottom: .85rem;
    }
    .git-modal-box input[type=text] {
      width: 100%; padding: .55rem .75rem; border: 1.5px solid var(--rule);
      border-radius: 8px; font-size: .875rem; font-family: 'Geist', sans-serif;
      color: var(--ink); outline: none; margin-bottom: .875rem; box-sizing: border-box;
      transition: border-color .15s;
    }
    .git-modal-box input[type=text]:focus { border-color: var(--warm); }
    .git-modal-actions {
      display: flex; gap: .5rem; justify-content: flex-end; flex-wrap: wrap;
    }
    .git-progress-list { list-style: none; padding: 0; margin: 0; }
    .git-progress-item {
      font-size: .82rem; padding: .3rem .4rem; border-radius: 6px;
      display: flex; align-items: center; gap: .45rem;
      opacity: 0; animation: gpFadeIn .2s ease forwards;
      color: var(--muted); margin-bottom: .2rem;
    }
    .git-progress-item.ok  { color: #166534; background: #f0fdf4; }
    .git-progress-item.warn { color: #92400e; background: #fff8f5; }
    .git-progress-item.err  { color: #991b1b; background: #fef2f2; }
    @keyframes gpFadeIn { to { opacity: 1; } }
    .btn-sm { padding: .35rem .85rem; font-size: .82rem; border-radius: 7px; }

    /* Git action buttons */
    .git-action-btn {
      width: 100%; display: flex; align-items: center; gap: .6rem;
      padding: .55rem .7rem; border-radius: 8px; border: none;
      cursor: pointer; text-align: left; font-family: inherit;
      transition: filter .15s, border-color .15s;
    }
    .git-action-btn svg, .git-action-btn i { flex-shrink: 0; }
    .git-action-primary { background: var(--warm); color: #fff; }
    .git-action-primary:hover { filter: brightness(.9); }
    .git-action-secondary { background: #fff; color: var(--ink); border: 1.5px solid var(--rule); }
    .git-action-secondary:hover { border-color: var(--warm); }
    .git-btn-label { font-size: .8rem; font-weight: 600; line-height: 1.2; }
    .git-btn-desc  { font-size: .67rem; opacity: .75; line-height: 1.3; margin-top: .1rem; }
    .git-action-primary .git-btn-desc { opacity: .85; color: #fff; }
    .git-action-secondary .git-btn-desc { color: var(--muted); }
    .git-branch-pill {
      display: inline-flex; align-items: center; gap: .25rem;
      font-size: .68rem; color: var(--muted); padding: .15rem 0 .35rem;
    }
  </style>
</head>
<body>
<div class="workspace">

  <!-- Header -->
  <div class="ws-header">
    <a href="dashboard.php" onclick="interceptExit(event, 'dashboard.php')"
       style="color:var(--muted);display:flex;align-items:center;gap:.3rem;text-decoration:none;">
      <i data-lucide="arrow-left" style="width:15px;height:15px;"></i>
    </a>
    <span class="sep">|</span>
    <i data-lucide="box" style="width:15px;height:15px;color:var(--warm);"></i>
    <strong><?= htmlspecialchars($appName) ?></strong>
    <span class="spacer"></span>
    <?php if ($hasGithub): ?>
    <button onclick="openSaveModal()" class="btn btn-secondary btn-xs"
            style="display:inline-flex;align-items:center;gap:.35rem;">
      <i data-lucide="git-branch" style="width:13px;height:13px;"></i>
      <span data-pt="Salvar versão" data-en="Save version">Salvar versão</span>
    </button>
    <?php endif; ?>
    <?php if ($devUrl): ?>
    <a href="<?= htmlspecialchars($devUrl) ?>" target="_blank"
       class="btn btn-secondary btn-xs" style="display:inline-flex;align-items:center;gap:.3rem;">
      <i data-lucide="external-link" style="width:13px;height:13px;"></i>
      <span data-pt="Abrir app" data-en="Open app">Abrir app</span>
    </a>
    <?php endif; ?>
  </div>

  <!-- Sidebar com instruções -->
  <div class="ws-sidebar">

    <?php if ($adminEmail && $adminPass): ?>
    <div class="instr-section" style="background:#fff8f5;border:1.5px solid #fbd3c3;border-radius:10px;padding:.875rem;">
      <h3 style="color:var(--warm);margin-bottom:.75rem;display:flex;align-items:center;gap:.4rem;">
        <i data-lucide="key-round" style="width:13px;height:13px;"></i>
        <span data-pt="Acesso inicial" data-en="Initial access">Acesso inicial</span>
      </h3>

      <?php if ($devUrl): ?>
      <a href="<?= htmlspecialchars($devUrl) ?>" target="_blank"
         style="display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:var(--warm);
                text-decoration:none;margin-bottom:.75rem;font-weight:500;">
        <i data-lucide="external-link" style="width:13px;height:13px;"></i>
        <?= htmlspecialchars($devUrl) ?>
      </a>
      <?php endif; ?>

      <div class="info-item" style="border-color:#fbd3c3;align-items:center;">
        <span class="lbl">E-mail</span>
        <span class="val" style="flex:1;"><?= htmlspecialchars($adminEmail) ?></span>
        <button onclick="copyText('<?= htmlspecialchars($adminEmail) ?>', this)" title="Copiar e-mail"
          style="background:none;border:none;cursor:pointer;padding:2px;color:var(--muted);flex-shrink:0;display:flex;">
          <i data-lucide="copy" style="width:13px;height:13px;"></i>
        </button>
      </div>

      <div class="info-item" style="border:none;align-items:center;">
        <span class="lbl">Senha</span>
        <span class="val" id="pass-display" style="flex:1;letter-spacing:.1em;">••••••••••••</span>
        <input type="text" id="pass-real" value="<?= htmlspecialchars($adminPass) ?>" readonly
          style="display:none;flex:1;background:none;border:none;padding:0;font-family:'Geist Mono',monospace;
                 font-size:.75rem;color:var(--ink);outline:none;">
        <div style="display:flex;gap:.25rem;flex-shrink:0;">
          <button onclick="togglePass()" title="Mostrar/ocultar senha"
            style="background:none;border:none;cursor:pointer;padding:2px;color:var(--muted);display:flex;" id="eye-btn">
            <i data-lucide="eye" style="width:14px;height:14px;"></i>
          </button>
          <button onclick="copyText('<?= htmlspecialchars($adminPass) ?>', this)" title="Copiar senha"
            style="background:none;border:none;cursor:pointer;padding:2px;color:var(--muted);display:flex;">
            <i data-lucide="copy" style="width:13px;height:13px;"></i>
          </button>
        </div>
      </div>

      <p style="font-size:.72rem;color:var(--muted);margin-top:.6rem;line-height:1.5;"
         data-pt="Troque a senha após o primeiro acesso."
         data-en="Change your password after first login.">
        Troque a senha após o primeiro acesso.
      </p>
    </div>
    <?php endif; ?>

    <div class="instr-section">
      <h3>
        <i data-lucide="play-circle" style="width:11px;height:11px;display:inline;"></i>
        <span data-pt="Como começar" data-en="How to start">Como começar</span>
      </h3>
      <p style="font-size:.8rem;color:var(--muted);margin-bottom:.75rem;"
         data-pt="Digite o comando abaixo no terminal para iniciar o Claude Code neste app:"
         data-en="Run the command below in the terminal to start Claude Code for this app:">
        Digite o comando abaixo no terminal para iniciar o Claude Code neste app:
      </p>
      <div class="cmd-block" onclick="copyCmd(this, 'claude')">
        <span>$ claude</span>
        <span class="copy-hint" data-pt="clique para copiar" data-en="click to copy">clique para copiar</span>
      </div>
    </div>

    <div class="instr-section">
      <h3>
        <i data-lucide="git-branch" style="width:11px;height:11px;display:inline;"></i>
        <span data-pt="Controle de versão" data-en="Version control">Controle de versão</span>
      </h3>

      <?php if (!$hasGit): ?>
        <p style="font-size:.78rem;color:var(--muted);"
           data-pt="Git não inicializado neste app." data-en="Git not initialized for this app.">
          Git não inicializado neste app.</p>

      <?php elseif (!$hasGithub): ?>
        <!-- Git initialized but no remote yet -->
        <?php if ($hasGithubToken): ?>
          <!-- Quick link: just enter the repo name -->
          <div style="background:#f8f9fa;border:1px solid var(--rule);border-radius:9px;
                      padding:.65rem .75rem;font-size:.75rem;color:var(--muted);line-height:1.6;">
            <strong style="color:var(--ink);display:block;margin-bottom:.3rem;"
                    data-pt="Vincular ao GitHub" data-en="Link to GitHub">Vincular ao GitHub</strong>
            <?php if ($ghOwner): ?>
              <p style="font-size:.72rem;color:var(--muted);margin-bottom:.45rem;">
                <span data-pt="Repositório em " data-en="Repository under ">Repositório em </span>
                <strong style="color:var(--ink);"><?= htmlspecialchars($ghOwner) ?></strong>
                &nbsp;·&nbsp;
                <a href="settings.php" style="color:var(--warm);text-decoration:none;font-size:.7rem;"
                   data-pt="alterar" data-en="change">alterar</a>
              </p>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:.35rem;">
              <?php if ($ghOwner): ?>
                <span style="font-size:.72rem;color:var(--muted);white-space:nowrap;
                             font-family:'Geist Mono',monospace;"><?= htmlspecialchars($ghOwner) ?>/</span>
              <?php endif; ?>
              <input id="link-repo-input" type="text"
                     placeholder="<?= $ghOwner ? 'nome-do-repo' : 'owner/nome-do-repo' ?>"
                     style="flex:1;padding:.32rem .55rem;border:1.5px solid var(--rule);border-radius:6px;
                            font-size:.75rem;font-family:'Geist Mono',monospace;background:#fff;
                            color:var(--ink);outline:none;"
                     onfocus="this.style.borderColor='var(--warm)'"
                     onblur="this.style.borderColor='var(--rule)'"
                     onkeydown="if(event.key==='Enter') linkRepo()">
              <button id="link-repo-btn" onclick="linkRepo()"
                style="padding:.32rem .65rem;background:var(--warm);color:#fff;border:none;
                       border-radius:6px;font-size:.75rem;font-weight:600;cursor:pointer;
                       white-space:nowrap;flex-shrink:0;"
                data-pt="Vincular" data-en="Link">Vincular</button>
            </div>
            <p id="link-repo-err"
               style="display:none;color:#c62828;font-size:.7rem;margin-top:.35rem;"></p>
          </div>
        <?php else: ?>
          <div style="background:#f8f9fa;border:1px solid var(--rule);border-radius:8px;
                      padding:.65rem .75rem;font-size:.75rem;color:var(--muted);line-height:1.6;">
            <strong style="color:var(--ink);display:block;margin-bottom:.2rem;"
                    data-pt="Sem repositório remoto" data-en="No remote repository">Sem repositório remoto</strong>
            <span class="pt-only">Configure o GitHub em Settings para vincular um repositório.</span>
            <span class="en-only">Configure GitHub in Settings to link a repository.</span>
            <br>
            <a href="settings.php" style="color:var(--warm);font-weight:500;text-decoration:none;
                                          margin-top:.4rem;display:inline-block;"
               data-pt="Configurar GitHub →" data-en="Set up GitHub →">
              Configurar GitHub →
            </a>
          </div>
        <?php endif; ?>

      <?php else: ?>

        <!-- Repo row — link + key status -->
        <div style="display:flex;align-items:center;justify-content:space-between;gap:.4rem;
                    padding:.5rem .6rem;background:var(--cream);border-radius:8px;margin-bottom:.6rem;">
          <?php if ($githubBrowserUrl): ?>
            <a href="<?= htmlspecialchars($githubBrowserUrl) ?>" target="_blank"
               style="display:flex;align-items:center;gap:.4rem;font-size:.75rem;color:var(--ink);
                      text-decoration:none;font-family:'Geist Mono',monospace;overflow:hidden;min-width:0;">
              <svg viewBox="0 0 24 24" style="width:13px;height:13px;fill:var(--ink);flex-shrink:0;" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 0C5.37 0 0 5.37 0 12c0 5.3 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61-.546-1.385-1.335-1.755-1.335-1.755-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 21.795 24 17.295 24 12c0-6.63-5.37-12-12-12"/>
              </svg>
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= htmlspecialchars(preg_replace('#https://github\.com/#', '', $githubBrowserUrl)) ?>
              </span>
              <i data-lucide="external-link" style="width:11px;height:11px;flex-shrink:0;stroke:var(--muted);"></i>
            </a>
          <?php else: ?>
            <span style="font-size:.73rem;color:var(--muted);font-family:'Geist Mono',monospace;
                         overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= htmlspecialchars($gitRemote) ?>
            </span>
          <?php endif; ?>

          <!-- Auth / deploy key badge -->
          <?php if ($isPatAuth): ?>
            <span title="Using HTTPS with Personal Access Token — no SSH key needed"
                  style="display:inline-flex;align-items:center;gap:.25rem;flex-shrink:0;
                         font-size:.68rem;font-weight:600;color:#1e40af;background:#eff6ff;
                         border:1px solid #bfdbfe;border-radius:5px;padding:.15rem .4rem;white-space:nowrap;">
              <i data-lucide="lock" style="width:11px;height:11px;stroke:#1e40af;"></i>
              <span>HTTPS</span>
            </span>
          <?php elseif ($hasDeployKey): ?>
            <span title="Deploy key found at <?= htmlspecialchars($deployKeyFile) ?>"
                  style="display:inline-flex;align-items:center;gap:.25rem;flex-shrink:0;
                         font-size:.68rem;font-weight:600;color:#2e7d32;background:#e8f5e9;
                         border:1px solid #c8e6c9;border-radius:5px;padding:.15rem .4rem;white-space:nowrap;">
              <i data-lucide="key-round" style="width:11px;height:11px;stroke:#2e7d32;"></i>
              <span data-pt="Chave OK" data-en="Key OK">Chave OK</span>
            </span>
          <?php else: ?>
            <span title="Deploy key not found at <?= htmlspecialchars($deployKeyFile) ?>"
                  style="display:inline-flex;align-items:center;gap:.25rem;flex-shrink:0;
                         font-size:.68rem;font-weight:600;color:#b45309;background:#fff7ed;
                         border:1px solid #fed7aa;border-radius:5px;padding:.15rem .4rem;white-space:nowrap;">
              <i data-lucide="key-round" style="width:11px;height:11px;stroke:#b45309;"></i>
              <span data-pt="Sem chave" data-en="No key">Sem chave</span>
            </span>
          <?php endif; ?>
        </div>

        <!-- Git status line -->
        <div id="git-status-box" style="font-size:.75rem;color:var(--muted);
             padding:.4rem 0;border-bottom:1px solid var(--rule);margin-bottom:.6rem;line-height:1.7;">
          <span id="git-status-text" data-pt="Verificando..." data-en="Checking...">Verificando...</span>
        </div>

        <?php if ($canPushPull): ?>

          <!-- Branch indicator -->
          <div class="git-branch-pill">
            <i data-lucide="git-branch" style="width:11px;height:11px;"></i>
            Branch: <strong id="git-branch-name" style="color:var(--ink);">dev</strong>
          </div>

          <!-- Enviar para o GitHub (commit + push) -->
          <button onclick="openSaveModal()" class="git-action-btn git-action-primary" style="margin-bottom:.4rem;">
            <i data-lucide="upload-cloud" style="width:16px;height:16px;"></i>
            <div>
              <div class="git-btn-label">Enviar para o GitHub</div>
              <div class="git-btn-desc">Salva uma cópia do seu código na nuvem</div>
            </div>
          </button>

          <!-- Baixar do GitHub (pull) -->
          <button onclick="openPullModal()" class="git-action-btn git-action-secondary">
            <i data-lucide="download-cloud" style="width:16px;height:16px;"></i>
            <div>
              <div class="git-btn-label">Baixar do GitHub</div>
              <div class="git-btn-desc">Traz atualizações feitas por outros</div>
            </div>
          </button>

        <?php else: ?>

          <!-- Deploy key missing — explain + offer auto-add if token available -->
          <div style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:9px;
                      padding:.75rem;margin-bottom:.5rem;">
            <div style="font-size:.78rem;font-weight:600;color:#92400e;margin-bottom:.35rem;
                        display:flex;align-items:center;gap:.4rem;">
              <i data-lucide="alert-triangle" style="width:13px;height:13px;stroke:#92400e;"></i>
              <span data-pt="Deploy key não adicionada" data-en="Deploy key not added">Deploy key não adicionada</span>
            </div>
            <p style="font-size:.73rem;color:#78350f;line-height:1.55;margin-bottom:.5rem;"
               data-pt="O repositório está configurado mas a chave SSH ainda não foi autorizada no GitHub. O push vai falhar até isso ser resolvido."
               data-en="The repository is configured but the SSH key has not been authorized on GitHub yet. Push will fail until this is resolved.">
              O repositório está configurado mas a chave SSH ainda não foi autorizada no GitHub. O push vai falhar até isso ser resolvido.
            </p>
            <div id="key-add-progress" style="display:none;margin-bottom:.5rem;" class="git-progress-list"></div>
            <div id="key-add-btns">
              <?php if ($hasGithubToken): ?>
                <button onclick="autoAddDeployKey()"
                  style="width:100%;padding:.45rem .75rem;background:#92400e;color:#fff;
                         border:none;border-radius:7px;font-size:.78rem;font-weight:600;
                         cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem;">
                  <i data-lucide="key-round" style="width:13px;height:13px;"></i>
                  <span data-pt="Adicionar chave automaticamente" data-en="Auto-add deploy key">Adicionar chave automaticamente</span>
                </button>
              <?php else: ?>
                <p style="font-size:.72rem;color:#92400e;line-height:1.5;">
                  <strong data-pt="Opção 1:" data-en="Option 1:">Opção 1:</strong>
                  <span data-pt=" Configure GitHub em " data-en=" Configure GitHub in "> Configure GitHub em </span>
                  <a href="settings.php" style="color:#92400e;font-weight:600;"
                     data-pt="Settings" data-en="Settings">Settings</a>
                  <span data-pt=" para adicionar automaticamente." data-en=" to add automatically."> para adicionar automaticamente.</span>
                  <br><br>
                  <strong data-pt="Opção 2 (manual):" data-en="Option 2 (manual):">Opção 2 (manual):</strong>
                  <span data-pt=" Edite o app no dashboard e copie a Deploy Key."
                        data-en=" Edit the app in the dashboard and copy the Deploy Key.">
                    Edite o app no dashboard e copie a Deploy Key.</span>
                  <a href="dashboard.php" style="color:#92400e;font-weight:600;display:block;margin-top:.4rem;"
                     data-pt="Ir ao dashboard →" data-en="Go to dashboard →">Ir ao dashboard →</a>
                </p>
              <?php endif; ?>
            </div>
          </div>

        <?php endif; /* $canPushPull */ ?>

      <?php endif; /* $hasGit / $hasGithub */ ?>

    </div>

    <div class="instr-section">
      <h3>
        <i data-lucide="database" style="width:11px;height:11px;display:inline;"></i>
        <span data-pt="Banco de dados" data-en="Database">Banco de dados</span>
      </h3>
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
          <span data-pt="Abrir banco de dados" data-en="Open database">Abrir banco de dados</span>
        </a>
      </div>
    </div>

    <div class="instr-section">
      <h3>
        <i data-lucide="folder" style="width:11px;height:11px;display:inline;"></i>
        <span data-pt="Informações" data-en="Information">Informações</span>
      </h3>
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
      <h3>
        <i data-lucide="sticky-note" style="width:11px;height:11px;display:inline;"></i>
        <span data-pt="Notas do projeto" data-en="Project notes">Notas do projeto</span>
      </h3>
      <div class="notes-text"><?= htmlspecialchars($notes) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($rules): ?>
    <div class="instr-section">
      <h3>
        <i data-lucide="shield-check" style="width:11px;height:11px;display:inline;"></i>
        <span data-pt="Regras" data-en="Rules">Regras</span>
      </h3>
      <div class="notes-text"><?= htmlspecialchars($rules) ?></div>
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
        <p>
          <span class="pt-only">Terminal não disponível.<br>Provisione o app primeiro.</span>
          <span class="en-only">Terminal unavailable.<br>Provision the app first.</span>
        </p>
        <a href="dashboard.php" class="btn btn-primary"
           data-pt="← Voltar" data-en="← Back">← Voltar</a>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- Exit Modal -->
<div id="exit-modal" class="git-overlay" style="display:none;">
  <div class="git-modal-box">
    <div id="exit-form">
      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--warm)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <h3 data-pt="Salvar antes de sair?" data-en="Save before leaving?">Salvar antes de sair?</h3>
      </div>
      <p>
        <span class="pt-only">Você tem <strong id="exit-changed-count">alterações</strong> não enviadas ao GitHub.
        Salvar agora garante que seu trabalho está protegido e pode ser acessado de qualquer lugar.</span>
        <span class="en-only">You have <strong id="exit-changed-count-en">changes</strong> not pushed to GitHub.
        Saving now ensures your work is protected and accessible from anywhere.</span>
      </p>
      <input type="text" id="exit-msg"
             data-pt="Descreva o que foi feito (opcional)"
             data-en="Describe what was done (optional)"
             placeholder="Descreva o que foi feito (opcional)">
      <div class="git-modal-actions">
        <button onclick="closeExitModal()" class="btn btn-ghost btn-xs btn-sm"
                data-pt="Cancelar" data-en="Cancel">Cancelar</button>
        <button onclick="leaveWithoutSave()" class="btn btn-secondary btn-xs btn-sm"
                data-pt="Sair sem salvar" data-en="Leave without saving">Sair sem salvar</button>
        <button onclick="saveAndLeave()" class="btn btn-primary btn-xs btn-sm"
                style="display:inline-flex;align-items:center;gap:.4rem;">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span data-pt="Salvar e sair" data-en="Save and leave">Salvar e sair</span>
        </button>
      </div>
    </div>
    <div id="exit-progress" class="git-progress-list" style="display:none;"></div>
  </div>
</div>

<!-- Pull Modal -->
<div id="pull-modal" class="git-overlay" style="display:none;">
  <div class="git-modal-box">
    <div id="pull-form">
      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--warm)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <h3>Baixar do GitHub</h3>
      </div>
      <p>Isso traz para cá as atualizações que foram enviadas ao GitHub — por você de outro computador, ou por outra pessoa. Seu código local é mantido e as versões são unidas automaticamente.</p>
      <div style="background:#f5f5f5;border-radius:7px;padding:.5rem .75rem;font-size:.78rem;
                  color:var(--muted);margin-bottom:1rem;display:flex;align-items:center;gap:.4rem;">
        <i data-lucide="git-branch" style="width:12px;height:12px;"></i>
        Branch: <strong style="color:var(--ink);margin-left:.2rem;">dev</strong>
        <span style="margin-left:.4rem;opacity:.7;">— somente esta branch é usada pelo Fenor</span>
      </div>
      <div class="git-modal-actions">
        <button onclick="closePullModal()" class="btn btn-ghost btn-xs btn-sm">Cancelar</button>
        <button onclick="doPull()" class="btn btn-primary btn-xs btn-sm"
                style="display:inline-flex;align-items:center;gap:.4rem;">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Baixar agora
        </button>
      </div>
    </div>
    <div id="pull-progress" class="git-progress-list" style="display:none;padding-bottom:.25rem;"></div>
    <div id="pull-done-btn" style="display:none;text-align:center;padding-top:.75rem;">
      <button onclick="closePullModal()" class="btn btn-primary btn-xs btn-sm">Fechar</button>
    </div>
  </div>
</div>

<!-- Save Modal -->
<div id="save-modal" class="git-overlay" style="display:none;">
  <div class="git-modal-box">
    <div id="save-form">
      <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--warm)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <h3 data-pt="Salvar versão no GitHub" data-en="Save to GitHub">Salvar versão no GitHub</h3>
      </div>
      <p>
        <span class="pt-only">Seu código será salvo no GitHub com uma descrição da versão.
        Isso cria um histórico e protege seu trabalho contra perdas.</span>
        <span class="en-only">Your code will be saved to GitHub with a version description.
        This creates a history and protects your work from loss.</span>
      </p>
      <input type="text" id="save-msg"
             data-pt="ex: Adiciona tela de cadastro de clientes"
             data-en="e.g.: Add customer registration screen"
             placeholder="ex: Adiciona tela de cadastro de clientes">
      <div class="git-modal-actions">
        <button onclick="closeSaveModal()" class="btn btn-ghost btn-xs btn-sm"
                data-pt="Cancelar" data-en="Cancel">Cancelar</button>
        <button onclick="doSavePush()" class="btn btn-primary btn-xs btn-sm"
                style="display:inline-flex;align-items:center;gap:.4rem;">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          <span data-pt="Salvar agora" data-en="Save now">Salvar agora</span>
        </button>
      </div>
    </div>
    <div id="save-progress" class="git-progress-list" style="display:none;padding-top:.25rem;"></div>
    <div id="save-done-btn" style="display:none;text-align:center;padding-top:.75rem;">
      <button onclick="closeSaveModal()" class="btn btn-primary btn-xs btn-sm"
              data-pt="Fechar" data-en="Close">Fechar</button>
    </div>
  </div>
</div>

<script>
lucide.createIcons();
function t(pt, en) { return (window.studioLang ? studioLang() : 'pt') === 'en' ? en : pt; }

let _passVisible = false;
function togglePass() {
  _passVisible = !_passVisible;
  const disp = document.getElementById('pass-display');
  const real = document.getElementById('pass-real');
  const btn  = document.getElementById('eye-btn');
  if (_passVisible) {
    disp.style.display = 'none';
    real.style.display = '';
    btn.innerHTML = '<i data-lucide="eye-off" style="width:14px;height:14px;"></i>';
  } else {
    disp.style.display = '';
    real.style.display = 'none';
    btn.innerHTML = '<i data-lucide="eye" style="width:14px;height:14px;"></i>';
  }
  lucide.createIcons();
}

const _SVG_CHECK = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';

function doCopy(text) {
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(text);
  }
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;width:1px;height:1px;';
  document.body.appendChild(ta);
  ta.focus(); ta.select();
  try { document.execCommand('copy'); } catch(e) {}
  document.body.removeChild(ta);
  return Promise.resolve();
}

function showTooltip(btn) {
  const tip = document.createElement('div');
  tip.textContent = t('Copiado!', 'Copied!');
  tip.style.cssText = [
    'position:fixed',
    'background:#1a1613',
    'color:#fff',
    'font-size:.72rem',
    'font-family:Geist,sans-serif',
    'padding:.3rem .65rem',
    'border-radius:6px',
    'pointer-events:none',
    'z-index:9999',
    'opacity:0',
    'transition:opacity .12s',
    'white-space:nowrap',
  ].join(';');
  document.body.appendChild(tip);
  const r = btn.getBoundingClientRect();
  tip.style.left = (r.left + r.width / 2 - tip.offsetWidth / 2) + 'px';
  tip.style.top  = (r.top - tip.offsetHeight - 8) + 'px';
  requestAnimationFrame(() => tip.style.opacity = '1');
  setTimeout(() => {
    tip.style.opacity = '0';
    setTimeout(() => tip.remove(), 150);
  }, 1400);
}

function copyText(text, btn) {
  doCopy(text).then(() => {
    if (!btn) return;
    showTooltip(btn);
    const orig = btn.innerHTML;
    btn.innerHTML = _SVG_CHECK;
    setTimeout(() => btn.innerHTML = orig, 1500);
  });
}

function copyCmd(el, cmd) {
  const hint = el.querySelector('.copy-hint');
  const orig  = hint.textContent;
  doCopy(cmd).then(() => {
    hint.textContent = t('✓ Copiado!', '✓ Copied!');
    el.classList.add('copied');
    setTimeout(() => { hint.textContent = orig; el.classList.remove('copied'); }, 1500);
  });
}

// === GIT WORKFLOW ===

const _appName   = '<?= addslashes($appName) ?>';
const _hasGithub = <?= $hasGithub ? 'true' : 'false' ?>;
const _gitRemote = '<?= addslashes($gitRemote) ?>';
const _isPatAuth = <?= $isPatAuth ? 'true' : 'false' ?>;
let _gitChanged  = 0;
let _exitUrl     = null;

const _SVG_WARN = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
const _SVG_ERR  = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';

function checkGitStatus() {
  const statusText = document.getElementById('git-status-text');
  if (!statusText) return;

  fetch('api/git.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({name: _appName, action: 'status'})
  })
  .then(r => r.json())
  .then(data => {
    if (!data.success) return;
    const s = data.status || {};
    _gitChanged = parseInt(s.changed || '0', 10);

    // Atualiza indicador de branch na sidebar
    if (s.branch) {
      const branchEl = document.getElementById('git-branch-name');
      if (branchEl) {
        branchEl.textContent = s.branch;
        branchEl.style.color = s.branch !== 'dev' ? '#b45309' : 'var(--ink)';
      }
    }

    const parts = [];
    if (_gitChanged > 0) {
      parts.push('<span style="color:var(--warm);font-weight:600;">' + _gitChanged + t(' arquivo(s) com alterações', ' file(s) changed') + '</span>');
    } else {
      parts.push('<span style="color:#166534;">✓ ' + t('Sem alterações locais', 'No local changes') + '</span>');
    }
    if (s.last) {
      const p = s.last.split('|');
      if (p[2]) parts.push(t('Último commit: ', 'Last commit: ') + p[2]);
    }
    if (statusText) statusText.innerHTML = parts.join(' &nbsp;·&nbsp; ');
  })
  .catch(() => {
    if (statusText) statusText.textContent = t('Status não disponível.', 'Status unavailable.');
  });
}

function animateGitProgress(output, overallSuccess, containerEl, doneCb) {
  // Separa linhas sem trim para preservar indentação de comandos
  const rawLines = output.split('\n').filter(function(l) { return l.length > 0; });
  if (!rawLines.length) { if (doneCb) doneCb(overallSuccess); return; }

  rawLines.forEach(function(raw, i) {
    setTimeout(function() {
      const line = raw.trim();
      const div  = document.createElement('div');
      div.className = 'git-progress-item';

      if (line.indexOf('✓') >= 0) {
        // Linha de sucesso
        div.classList.add('ok');
        div.innerHTML = _SVG_CHECK + ' ' + line.replace('✓', '').trim();

      } else if (/^!\s{2,}/.test(raw)) {
        // Linha de instrução/comando (começa com "!   " — indentada)
        // Mostra como bloco de código dentro do card de erro
        div.classList.add('warn');
        div.style.cssText = 'font-family:\'Geist Mono\',monospace;font-size:.75rem;'
          + 'background:#fff3cd;color:#856404;border-left:3px solid #ffc107;'
          + 'padding:.25rem .5rem .25rem .6rem;border-radius:0 4px 4px 0;';
        div.textContent = line.replace(/^!\s+/, '');

      } else if (line.charAt(0) === '!') {
        // Linha de aviso/erro principal
        div.classList.add('err');
        div.innerHTML = _SVG_ERR + ' ' + line.replace(/^!\s*/, '');

      } else {
        div.innerHTML = '· ' + line;
      }

      containerEl.appendChild(div);

      if (i === rawLines.length - 1) {
        setTimeout(function() {
          if (!overallSuccess) {
            // Linha final de erro — só adiciona se não for redundante
            const fin = document.createElement('div');
            fin.className = 'git-progress-item err';
            fin.innerHTML = _SVG_ERR + ' <b>' + t('Algo deu errado — leia as instruções acima.', 'Something went wrong — read the instructions above.') + '</b>';
            containerEl.appendChild(fin);
          }
          if (doneCb) setTimeout(function() { doneCb(overallSuccess); }, 350);
        }, 220);
      }
    }, i * 170);
  });
}

function runGit(action, message, containerEl, doneCb) {
  const body = {name: _appName, action: action};
  if (message) body.message = message;

  if (containerEl) { containerEl.style.display = ''; containerEl.innerHTML = ''; }

  fetch('api/git.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(body)
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    const out = (data.output || '').trim();
    if (containerEl && out) {
      animateGitProgress(out, data.success, containerEl, doneCb);
    } else {
      if (doneCb) doneCb(data.success);
    }
    if (action === 'pull' && data.success) setTimeout(checkGitStatus, 600);
  })
  .catch(function() {
    if (containerEl) {
      const d = document.createElement('div');
      d.className = 'git-progress-item err';
      d.innerHTML = _SVG_ERR + ' ' + t('Erro ao conectar com o servidor.', 'Error connecting to the server.');
      containerEl.appendChild(d);
    }
    if (doneCb) doneCb(false);
  });
}

// Pull modal
function openPullModal() {
  document.getElementById('pull-form').style.display = '';
  document.getElementById('pull-progress').style.display = 'none';
  document.getElementById('pull-progress').innerHTML = '';
  document.getElementById('pull-done-btn').style.display = 'none';
  document.getElementById('pull-modal').style.display = 'flex';
}

function closePullModal() {
  document.getElementById('pull-modal').style.display = 'none';
  checkGitStatus();
}

function doPull() {
  const progress = document.getElementById('pull-progress');
  document.getElementById('pull-form').style.display = 'none';
  progress.style.display = '';

  runGit('pull', null, progress, function(success) {
    // Sempre mostra o botão Fechar — sucesso ou erro o usuário precisa ver o resultado
    document.getElementById('pull-done-btn').style.display = '';
    if (success) checkGitStatus();
  });
}

// Save modal
function openSaveModal() {
  document.getElementById('save-form').style.display = '';
  document.getElementById('save-progress').style.display = 'none';
  document.getElementById('save-progress').innerHTML = '';
  document.getElementById('save-done-btn').style.display = 'none';
  document.getElementById('save-msg').value = '';
  document.getElementById('save-modal').style.display = 'flex';
  setTimeout(function() { document.getElementById('save-msg').focus(); }, 60);
}

function closeSaveModal() {
  document.getElementById('save-modal').style.display = 'none';
  checkGitStatus();
}

function doSavePush() {
  const rawMsg = document.getElementById('save-msg').value.trim();
  const msg    = rawMsg || (t('Atualização ', 'Update ') + new Date().toLocaleDateString(t('pt-BR', 'en-US')));
  const progress = document.getElementById('save-progress');

  document.getElementById('save-form').style.display = 'none';
  progress.style.display = '';

  runGit('push', msg, progress, function(success) {
    document.getElementById('save-done-btn').style.display = '';
    if (success) checkGitStatus();
  });
}

// Exit modal
function interceptExit(event, url) {
  event.preventDefault();
  _exitUrl = url;

  if (!_hasGithub || _gitChanged === 0) {
    window.location.href = url;
    return;
  }

  const countEl = document.getElementById('exit-changed-count');
  if (countEl) countEl.textContent = _gitChanged + t(' arquivo(s) com alterações', ' file(s) changed');
  const countElEn = document.getElementById('exit-changed-count-en');
  if (countElEn) countElEn.textContent = _gitChanged + ' file(s) changed';
  document.getElementById('exit-form').style.display = '';
  document.getElementById('exit-progress').style.display = 'none';
  document.getElementById('exit-progress').innerHTML = '';
  document.getElementById('exit-msg').value = '';
  document.getElementById('exit-modal').style.display = 'flex';
}

function closeExitModal() {
  document.getElementById('exit-modal').style.display = 'none';
}

function leaveWithoutSave() {
  window.location.href = _exitUrl || 'dashboard.php';
}

function saveAndLeave() {
  const rawMsg = document.getElementById('exit-msg').value.trim();
  const msg    = rawMsg || (t('Atualização ', 'Update ') + new Date().toLocaleDateString(t('pt-BR', 'en-US')));
  const progress = document.getElementById('exit-progress');

  document.getElementById('exit-form').style.display = 'none';
  progress.style.display = '';

  runGit('push', msg, progress, function(success) {
    setTimeout(function() {
      window.location.href = _exitUrl || 'dashboard.php';
    }, success ? 800 : 2200);
  });
}

// ── Auto-add deploy key via GitHub API ────────────────────────────────────
function autoAddDeployKey() {
  const progress = document.getElementById('key-add-progress');
  const btns     = document.getElementById('key-add-btns');
  if (progress) { progress.style.display = ''; progress.innerHTML = ''; }
  if (btns)     btns.style.display = 'none';

  const addItem = (text, state) => {
    const d = document.createElement('div');
    d.className = 'git-progress-item' + (state === 'ok' ? ' ok' : state === 'err' ? ' err' : '');
    d.innerHTML = (state === 'ok' ? _SVG_CHECK : state === 'err' ? _SVG_ERR : '· ') + ' ' + text;
    if (progress) progress.appendChild(d);
  };

  addItem(t('Conectando ao GitHub...', 'Connecting to GitHub...'));

  fetch('api/github.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'add-deploy-key', app: _appName, repo: _gitRemote})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      addItem(t('Chave adicionada com sucesso!', 'Deploy key added successfully!'), 'ok');
      setTimeout(() => window.location.reload(), 1200);
    } else {
      addItem(data.error || t('Falha ao adicionar chave.', 'Failed to add deploy key.'), 'err');
      if (btns) btns.style.display = '';
    }
  })
  .catch(() => {
    addItem(t('Erro de conexão.', 'Connection error.'), 'err');
    if (btns) btns.style.display = '';
  });
}

// ── Link repo by name (no full URL needed when PAT is configured) ─────────
function linkRepo() {
  const input = document.getElementById('link-repo-input');
  const btn   = document.getElementById('link-repo-btn');
  const err   = document.getElementById('link-repo-err');
  const repo  = input ? input.value.trim() : '';

  if (!repo) { if (input) input.focus(); return; }
  if (btn) { btn.disabled = true; btn.textContent = t('Vinculando...', 'Linking...'); }
  if (err) { err.style.display = 'none'; }

  fetch('api/git.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({name: _appName, action: 'set-remote', repo: repo})
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      window.location.reload();
    } else {
      const msg = data.error || t('Falha ao vincular repositório.', 'Failed to link repository.');
      if (err) { err.textContent = msg; err.style.display = ''; }
      if (btn) { btn.disabled = false; btn.textContent = t('Vincular', 'Link'); }
    }
  })
  .catch(() => {
    if (err) { err.textContent = t('Erro de conexão.', 'Connection error.'); err.style.display = ''; }
    if (btn) { btn.disabled = false; btn.textContent = t('Vincular', 'Link'); }
  });
}

window.addEventListener('load', checkGitStatus);

window.addEventListener('beforeunload', function(e) {
  if (_hasGithub && _gitChanged > 0) {
    e.preventDefault();
    e.returnValue = t('Você tem alterações não salvas no GitHub.', 'You have unsaved changes not pushed to GitHub.');
    return e.returnValue;
  }
});
</script>
</body>
</html>
