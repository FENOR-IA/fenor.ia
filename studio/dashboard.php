<?php
ini_set("session.gc_maxlifetime", 28800);
session_set_cookie_params(28800);
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

$config = require __DIR__ . '/config/config.php';
require  __DIR__ . '/config/helpers.php';

// Apps in database
$dbApps = [];
try {
    foreach (fenorDB()->query('SELECT * FROM fenor_apps ORDER BY created_at DESC')->fetchAll() as $row) {
        $dbApps[$row['name']] = $row;
    }
} catch (Throwable $e) { $dbApps = []; }

$appsJson = json_encode($dbApps, JSON_HEX_TAG | JSON_HEX_AMP);

// Apps in filesystem
$fsApps = [];
foreach (loadApps($config['apps_path']) as $app) {
    $fsApps[$app['name']] = $app;
}

// Merge DB + filesystem
$apps = [];
foreach ($dbApps as $name => $meta) {
    $apps[] = [
        'name'        => $name,
        'description' => $meta['description'] ?? '',
        'github_repo' => $meta['github_repo'] ?? '',
        'language'    => $meta['language']    ?? 'pt',
        'status'      => $meta['status']      ?? 'registered',
        'envs'        => $fsApps[$name]['envs'] ?? [],
    ];
}
foreach ($fsApps as $name => $fsApp) {
    if (!isset($dbApps[$name])) {
        $apps[] = array_merge($fsApp, ['description' => '', 'github_repo' => '', 'language' => 'pt', 'status' => 'provisioned']);
    }
}

$total  = count($apps);
$devCnt = count(array_filter($apps, fn($a) => isset($a['envs']['dev'])));
$hmlCnt = count(array_filter($apps, fn($a) => isset($a['envs']['hml'])));
$prdCnt = count(array_filter($apps, fn($a) => isset($a['envs']['prd'])));

require_once __DIR__ . '/config/db.php';
$_ghSettings      = fenorSettings();
$_hasGithubToken  = !empty(trim($_ghSettings['GITHUB_TOKEN'] ?? ''));
$_ghOwner         = trim($_ghSettings['GITHUB_ORG'] ?? '') ?: trim($_ghSettings['GITHUB_USER'] ?? '');
?>
<!DOCTYPE html>
<html lang="en" data-lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fenor Studio</title>
  <link rel="icon" type="image/svg+xml" href="assets/img/fenor-ia-favicon-terracota.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap">
  <link rel="stylesheet" href="assets/css/studio.css">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <style>
    /* Wizard — progress indicator */
    .wiz-progress { display:flex; align-items:center; gap:0; margin-bottom:1.5rem; }
    .wiz-step { display:flex; align-items:center; gap:.5rem; font-size:.8rem; color:var(--muted); }
    .wiz-step .wiz-num {
      width:26px; height:26px; border-radius:50%;
      background:var(--rule); color:var(--muted);
      display:flex; align-items:center; justify-content:center;
      font-size:.75rem; font-weight:700; flex-shrink:0; transition:background .2s, color .2s;
    }
    .wiz-step.active .wiz-num  { background:var(--warm); color:#fff; }
    .wiz-step.done  .wiz-num   { background:#d4edda; color:var(--success); }
    .wiz-step.active .wiz-label { color:var(--ink); font-weight:500; }
    .wiz-connector { flex:1; height:2px; background:var(--rule); margin:0 .5rem; transition:background .2s; }
    .wiz-connector.done { background:var(--warm); }

    /* Wizard — module cards */
    .mod-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; margin-top:.4rem; }
    .mod-card {
      display:flex; flex-direction:column; align-items:center; gap:.35rem;
      padding:.7rem .4rem; border:2px solid var(--rule); border-radius:10px;
      cursor:pointer; font-size:.75rem; font-weight:500; text-align:center;
      color:var(--muted); background:#fff; transition:all .15s; user-select:none; position:relative;
    }
    .mod-card input { display:none; }
    .mod-card svg   { width:20px; height:20px; stroke:var(--muted); transition:stroke .15s; }
    .mod-card:hover { border-color:var(--ember); color:var(--ink); }
    .mod-card:hover svg { stroke:var(--ember); }
    .mod-card.sel   { border-color:var(--warm); background:#fff1ec; color:var(--warm); }
    .mod-card.sel svg { stroke:var(--warm); }
    /* Disabled state (EN template — module not available yet) */
    .mod-card--disabled {
      opacity:.45; cursor:not-allowed; pointer-events:none;
      border-color:var(--rule) !important; background:#fafaf9 !important; color:var(--muted) !important;
    }
    .mod-card--disabled svg { stroke:var(--rule) !important; }
    .mod-card .soon-badge {
      display:none; position:absolute; top:3px; right:3px;
      font-size:.55rem; font-weight:700; background:var(--rule); color:var(--muted);
      padding:.1rem .3rem; border-radius:4px; letter-spacing:.04em;
    }
    .mod-card--disabled .soon-badge { display:block; }

    /* Wizard — option cards (radio) */
    .opt-grid { display:flex; flex-direction:column; gap:.4rem; margin-top:.4rem; }
    .opt-card {
      display:flex; align-items:center; gap:.875rem;
      padding:.65rem .875rem; border:2px solid var(--rule); border-radius:9px;
      cursor:pointer; background:#fff; transition:all .15s; user-select:none;
    }
    .opt-card input { display:none; }
    .opt-card .opt-icon { flex-shrink:0; width:32px; height:32px; border-radius:7px;
      background:var(--cream); display:flex; align-items:center; justify-content:center; transition:background .15s; }
    .opt-card .opt-icon svg { width:16px; height:16px; stroke:var(--muted); transition:stroke .15s; }
    .opt-card strong { display:block; font-size:.8125rem; color:var(--ink); line-height:1.3; }
    .opt-card small  { display:block; font-size:.73rem; color:var(--muted); line-height:1.3; margin-top:.1rem; }
    .opt-card:hover { border-color:var(--ember); }
    .opt-card:hover .opt-icon { background:#fde8df; }
    .opt-card:hover .opt-icon svg { stroke:var(--ember); }
    .opt-card.sel { border-color:var(--warm); background:#fff8f5; }
    .opt-card.sel .opt-icon { background:#fde8df; }
    .opt-card.sel .opt-icon svg { stroke:var(--warm); }

    /* Language selector cards */
    .lang-opt-grid { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; margin-top:.4rem; }
    .lang-opt-card {
      display:flex; align-items:center; gap:.75rem;
      padding:.75rem; border:2px solid var(--rule); border-radius:9px;
      cursor:pointer; background:#fff; transition:all .15s; user-select:none;
    }
    .lang-opt-card input { display:none; }
    .lang-opt-card .lang-flag { font-size:1.4rem; flex-shrink:0; }
    .lang-opt-card strong { display:block; font-size:.8125rem; color:var(--ink); }
    .lang-opt-card small  { display:block; font-size:.72rem; color:var(--muted); margin-top:.1rem; line-height:1.4; }
    .lang-opt-card:hover { border-color:var(--ember); }
    .lang-opt-card.sel   { border-color:var(--warm); background:#fff8f5; }

    /* Wizard inline toggle */
    .toggle-row { display:flex; gap:.5rem; margin-top:.4rem; }
    .toggle-opt {
      flex:1; display:flex; align-items:center; justify-content:center; gap:.4rem;
      padding:.55rem; border:2px solid var(--rule); border-radius:8px;
      cursor:pointer; font-size:.8125rem; font-weight:500; color:var(--muted);
      background:#fff; transition:all .15s; user-select:none;
    }
    .toggle-opt input { display:none; }
    .toggle-opt:hover { border-color:var(--ember); color:var(--ink); }
    .toggle-opt.sel   { border-color:var(--warm); background:#fff8f5; color:var(--warm); }

    .wiz-section-label {
      font-size:.7rem; font-weight:600; text-transform:uppercase;
      letter-spacing:.07em; color:var(--muted); margin-bottom:.1rem;
    }
    #form-wizard .field { margin-bottom:1.1rem; }

    /* Creation progress */
    @keyframes spin    { to { transform: rotate(360deg); } }
    @keyframes piIn    { to { opacity:1; transform:translateY(0); } }
    @keyframes scaleIn { to { opacity:1; transform:scale(1); } }
    #modal-progress { margin-top:1rem; }
    .prog-spinner { display:flex; flex-direction:column; align-items:center; gap:.875rem; padding:2rem 0; color:var(--muted); }
    .prog-spinner svg { animation: spin .9s linear infinite; }
    .prog-spinner span { font-size:.875rem; }
    #progress-items { padding:.25rem 0; }
    .pi-item {
      display:flex; align-items:center; gap:.75rem; padding:.45rem 0;
      border-bottom:1px solid var(--rule); font-size:.8125rem;
      opacity:0; transform:translateY(5px); animation: piIn .22s ease forwards;
    }
    .pi-item:last-child { border-bottom:none; }
    .pi-icon { width:28px; height:28px; border-radius:7px; flex-shrink:0; background:var(--cream); display:flex; align-items:center; justify-content:center; }
    .pi-icon svg { width:14px; height:14px; stroke:var(--muted); }
    .pi-label { flex:1; color:var(--ink); }
    .pi-check svg { width:16px; height:16px; stroke:var(--success); }
    .prog-success { text-align:center; padding:1.25rem 0 .5rem; opacity:0; transform:scale(.94); animation: scaleIn .3s .1s ease forwards; }
    .prog-success .check-circle { width:52px; height:52px; border-radius:50%; background:#e8f5e9; margin:0 auto .875rem; display:flex; align-items:center; justify-content:center; }
    .prog-success .check-circle svg { width:26px; height:26px; stroke:var(--success); }
    .prog-success strong { display:block; font-size:.95rem; margin-bottom:.3rem; }
    .prog-success .prog-url { font-size:.8rem; color:var(--warm); text-decoration:none; display:inline-flex; align-items:center; gap:.3rem; }
    .prog-success .prog-url:hover { text-decoration:underline; }
    .wizard-check, .wizard-radio {
      display:flex; align-items:center; gap:.4rem; font-size:.875rem; cursor:pointer;
      padding:.25rem .4rem; border-radius:5px; transition:background .12s;
    }
    .wizard-check:hover, .wizard-radio:hover { background:var(--cream); }
    .wizard-check input, .wizard-radio input { margin:0; cursor:pointer; }
  </style>
</head>
<body>
<div class="layout">
  <?php $pageTitle = 'Apps'; include __DIR__ . '/partials/sidebar.php'; ?>
  <div class="main">
    <?php include __DIR__ . '/partials/topbar.php'; ?>
    <div class="content">

      <!-- Stats -->
      <div class="stats-grid" style="margin-bottom:1.5rem;">
        <div class="stat-card">
          <div class="label">Total</div>
          <div class="value"><?= $total ?></div>
          <div class="sub" data-pt="apps cadastrados" data-en="registered apps">apps cadastrados</div>
        </div>
        <div class="stat-card">
          <div class="label">DEV</div>
          <div class="value"><?= $devCnt ?></div>
          <div class="sub" data-pt="em desenvolvimento" data-en="in development">em desenvolvimento</div>
        </div>
        <div class="stat-card">
          <div class="label">HML</div>
          <div class="value"><?= $hmlCnt ?></div>
          <div class="sub" data-pt="em homologação" data-en="in staging">em homologação</div>
        </div>
        <div class="stat-card">
          <div class="label">PRD</div>
          <div class="value"><?= $prdCnt ?></div>
          <div class="sub" data-pt="em produção" data-en="in production">em produção</div>
        </div>
      </div>

      <!-- Apps table -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
        <p style="color:var(--muted);font-size:.875rem;"><?= $total ?> app(s)</p>
        <button class="btn btn-primary" onclick="openModal('new')" style="display:inline-flex;align-items:center;gap:.4rem;">
          <i data-lucide="plus-circle" style="width:16px;height:16px;"></i>
          <span data-pt="Novo app" data-en="New app">Novo app</span>
        </button>
      </div>

      <?php if (empty($apps)): ?>
        <div style="text-align:center;padding:4rem;color:var(--muted);">
          <p style="font-size:1.1rem;margin-bottom:1rem;" data-pt="Nenhum app criado ainda." data-en="No apps created yet.">Nenhum app criado ainda.</p>
          <button class="btn btn-primary" onclick="openModal('new')"
                  data-pt="Criar primeiro app →" data-en="Create first app →">Criar primeiro app →</button>
        </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>App</th>
              <th style="text-align:center;width:110px;" data-pt="Desenvolvimento" data-en="Development">Desenvolvimento</th>
              <th style="text-align:center;width:110px;" data-pt="Homologação" data-en="Staging">Homologação</th>
              <th style="text-align:center;width:110px;" data-pt="Produção" data-en="Production">Produção</th>
              <th style="text-align:center;width:70px;">Terminal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($apps as $app):
              $name        = $app['name'];
              $desc        = $app['description'] ?? '';
              $ghRepo      = $app['github_repo'] ?: ($app['envs']['dev']['github_repo'] ?? '');
              $ghUrl       = $ghRepo ? 'https://github.com/' . preg_replace('/^git@github\.com:|\.git$/', '', str_replace(':', '/', $ghRepo)) : '';
              $provisioned = ($app['status'] ?? 'registered') === 'provisioned';
              $devUrl      = $app['envs']['dev']['url']      ?? '';
              $hmlUrl      = $app['envs']['hml']['url']      ?? '';
              $prdUrl      = $app['envs']['prd']['url']      ?? '';
              $termUrl     = $app['envs']['dev']['terminal'] ?? '';
              $hasDev      = !empty($devUrl);
              $hasHml      = !empty($hmlUrl);
              $hasPrd      = !empty($prdUrl);
              $appLang     = $app['language'] ?? 'pt';
              $n           = htmlspecialchars($name);
            ?>
            <tr>
              <td>
                <span onclick="openModal('edit','<?= $n ?>')"
                      style="cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;padding:.2rem .4rem .2rem 0;border-radius:6px;transition:background .15s;"
                      onmouseover="this.style.background='var(--cream)'" onmouseout="this.style.background='none'">
                  <strong style="font-size:.95rem;"><?= $n ?></strong>
                  <i data-lucide="pencil" style="width:12px;height:12px;color:var(--warm);flex-shrink:0;"></i>
                </span>
                <span style="display:inline-block;font-size:.65rem;padding:.1rem .4rem;border-radius:4px;background:var(--cream);color:var(--muted);margin-left:.2rem;font-weight:600;letter-spacing:.04em;"><?= strtoupper($appLang) ?></span>
                <?php if ($desc): ?>
                  <br><span style="font-size:.78rem;color:var(--muted);"><?= htmlspecialchars($desc) ?></span>
                <?php endif; ?>
                <?php if ($ghUrl): ?>
                  <br><a href="<?= htmlspecialchars($ghUrl) ?>" target="_blank" style="font-size:.78rem;color:var(--muted);">GitHub ↗</a>
                <?php endif; ?>
              </td>

              <!-- DEV -->
              <td style="text-align:center;">
                <?php if (!$provisioned): ?>
                  <button onclick="openModal('provision','<?= $n ?>')" class="env-btn env-btn-action">
                    <i data-lucide="zap" style="width:14px;height:14px;"></i>
                    <span data-pt="Criar" data-en="Create">Criar</span>
                  </button>
                <?php elseif ($hasDev): ?>
                  <a href="<?= htmlspecialchars($devUrl) ?>" target="_blank" class="env-btn env-btn-dev">
                    <i data-lucide="external-link" style="width:14px;height:14px;"></i>
                    <span data-pt="Abrir" data-en="Open">Abrir</span>
                  </a>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>

              <!-- HML -->
              <td style="text-align:center;">
                <?php if ($hasHml): ?>
                  <a href="<?= htmlspecialchars($hmlUrl) ?>" target="_blank" class="env-btn env-btn-hml">
                    <i data-lucide="external-link" style="width:14px;height:14px;"></i>
                    <span data-pt="Abrir" data-en="Open">Abrir</span>
                  </a>
                <?php elseif ($provisioned && $hasDev): ?>
                  <button onclick="openModal('publish','<?= $n ?>','hml')" class="env-btn env-btn-publish">
                    <i data-lucide="upload-cloud" style="width:14px;height:14px;"></i>
                    <span data-pt="Publicar" data-en="Publish">Publicar</span>
                  </button>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>

              <!-- PRD -->
              <td style="text-align:center;">
                <?php if ($hasPrd): ?>
                  <a href="<?= htmlspecialchars($prdUrl) ?>" target="_blank" class="env-btn env-btn-prd">
                    <i data-lucide="external-link" style="width:14px;height:14px;"></i>
                    <span data-pt="Abrir" data-en="Open">Abrir</span>
                  </a>
                <?php elseif ($provisioned && $hasDev): ?>
                  <button onclick="openModal('publish','<?= $n ?>','prd')" class="env-btn env-btn-publish">
                    <i data-lucide="rocket" style="width:14px;height:14px;"></i>
                    <span data-pt="Publicar" data-en="Deploy">Publicar</span>
                  </button>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>

              <!-- Terminal -->
              <td style="text-align:center;">
                <?php if ($termUrl && $provisioned): ?>
                  <a href="workspace.php?app=<?= urlencode($name) ?>" class="env-btn env-btn-term">
                    <i data-lucide="terminal" style="width:16px;height:16px;"></i>
                  </a>
                <?php else: ?>
                  <span class="env-btn env-btn-off">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal-bg" id="modal">
  <div class="modal">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
      <h2 id="modal-title" style="margin:0;" data-pt="Novo app" data-en="New app">Novo app</h2>
    </div>

    <!-- Wizard progress indicator -->
    <div id="wiz-progress" style="display:none;" class="wiz-progress">
      <div class="wiz-step active" id="wp-s1">
        <span class="wiz-num">1</span>
        <span class="wiz-label" data-pt="Idioma" data-en="Language">Idioma</span>
      </div>
      <div class="wiz-connector" id="wp-line1"></div>
      <div class="wiz-step" id="wp-s2">
        <span class="wiz-num">2</span>
        <span class="wiz-label" data-pt="Nome" data-en="Name">Nome</span>
      </div>
      <div class="wiz-connector" id="wp-line2"></div>
      <div class="wiz-step" id="wp-s3">
        <span class="wiz-num">3</span>
        <span class="wiz-label" data-pt="Módulos" data-en="Modules">Módulos</span>
      </div>
      <div class="wiz-connector" id="wp-line3"></div>
      <div class="wiz-step" id="wp-s4">
        <span class="wiz-num">4</span>
        <span class="wiz-label" data-pt="Contexto" data-en="Context">Contexto</span>
      </div>
    </div>

    <!-- STEP 1: Language -->
    <div id="form-lang">
      <div class="field">
        <div class="wiz-section-label" data-pt="Idioma do app" data-en="App language">Idioma do app</div>
        <p style="font-size:.8rem;color:var(--muted);line-height:1.6;margin-bottom:.6rem;"
           data-pt="O template e o código gerado seguirão o idioma escolhido."
           data-en="The generated template and code will follow the chosen language.">
          O template e o código gerado seguirão o idioma escolhido.
        </p>
        <div class="lang-opt-grid" id="lang-opts">
          <label class="lang-opt-card sel" onclick="selectLangOpt(this,'pt')">
            <input type="radio" name="app-lang" value="pt" checked>
            <span class="lang-flag">🇧🇷</span>
            <span>
              <strong data-pt="Português" data-en="Portuguese">Português</strong>
              <small data-pt="Template elaborado — clientes, finanças, usuários"
                     data-en="Elaborate template — customers, finance, users">Template elaborado — clientes, finanças, usuários</small>
            </span>
          </label>
          <label class="lang-opt-card" onclick="selectLangOpt(this,'en')">
            <input type="radio" name="app-lang" value="en">
            <span class="lang-flag">🇺🇸</span>
            <span>
              <strong>English</strong>
              <small data-pt="Skeleton simples — login + dashboard. A comunidade expande."
                     data-en="Simple skeleton — login + dashboard. Community expands it.">Skeleton simples — login + dashboard. A comunidade expande.</small>
            </span>
          </label>
        </div>
      </div>
    </div>

    <!-- STEP 2: App name -->
    <div id="form-new" style="display:none;">
      <div style="background:var(--cream);border-radius:10px;padding:1rem 1.1rem;margin-bottom:1.25rem;display:flex;gap:.875rem;align-items:flex-start;">
        <i data-lucide="sparkles" style="width:20px;height:20px;stroke:var(--warm);flex-shrink:0;margin-top:.1rem;"></i>
        <div>
          <div style="font-weight:600;font-size:.875rem;margin-bottom:.25rem;"
               data-pt="Como funciona" data-en="How it works">Como funciona</div>
          <div style="font-size:.8rem;color:var(--muted);line-height:1.6;"
               data-pt="Em 3 passos você define o nome, escolhe os módulos e descreve o contexto. O Fenor prepara o ambiente e orienta o Claude."
               data-en="In 3 steps you define the name, choose modules and describe the context. Fenor sets up the environment and guides Claude.">
            Em 3 passos você define o nome, escolhe os módulos e descreve o contexto. O Fenor prepara o ambiente e orienta o Claude.
          </div>
        </div>
      </div>
      <div class="field">
        <label data-pt="Nome do app" data-en="App name">Nome do app</label>
        <input type="text" id="app-name" placeholder="my-app" autofocus>
        <small data-pt="Letras minúsculas, números e hífens. Ex: <strong>clinica-central</strong>, <strong>meu-crm</strong>"
               data-en="Lowercase letters, numbers and hyphens. E.g.: <strong>clinic-portal</strong>, <strong>my-crm</strong>">
          Letras minúsculas, números e hífens. Ex: <strong>clinica-central</strong>, <strong>meu-crm</strong>
        </small>
      </div>
    </div>

    <!-- STEP 3: Modules (wizard) -->
    <div id="form-wizard" style="display:none;">

      <div class="field">
        <div class="wiz-section-label" data-pt="Módulos do sistema" data-en="System modules">Módulos do sistema</div>
        <p style="font-size:.8rem;color:var(--muted);line-height:1.6;margin-bottom:.6rem;"
           data-pt="O app nasce com os módulos selecionados — telas, banco e rotas prontos."
           data-en="The app starts with the selected modules — screens, database and routes ready.">
          O app nasce com os módulos selecionados — telas, banco e rotas prontos.
        </p>
        <div id="mod-en-notice" style="display:none;background:#fff8f5;border:1px solid #fbd3c3;border-radius:8px;padding:.6rem .875rem;margin-bottom:.6rem;font-size:.78rem;color:var(--warm);">
          <span data-pt="Template EN: apenas Dashboard disponível. Módulos adicionais serão adicionados pela comunidade."
                data-en="EN template: only Dashboard available. Additional modules will be added by the community.">
            Template EN: apenas Dashboard disponível. Módulos adicionais serão adicionados pela comunidade.
          </span>
        </div>
        <div class="mod-grid">
          <label class="mod-card sel" onclick="toggleMod(this)">
            <input type="checkbox" name="mod" value="dashboard" checked>
            <span class="soon-badge">Soon</span>
            <i data-lucide="layout-dashboard"></i><span>Dashboard</span>
          </label>
          <label class="mod-card sel" onclick="toggleMod(this)">
            <input type="checkbox" name="mod" value="customers" checked>
            <span class="soon-badge">Soon</span>
            <i data-lucide="users"></i>
            <span data-pt="Clientes" data-en="Customers">Clientes</span>
          </label>
          <label class="mod-card sel" onclick="toggleMod(this)">
            <input type="checkbox" name="mod" value="users" checked>
            <span class="soon-badge">Soon</span>
            <i data-lucide="user-cog"></i>
            <span data-pt="Usuários" data-en="Users">Usuários</span>
          </label>
          <label class="mod-card" onclick="toggleMod(this)">
            <input type="checkbox" name="mod" value="finance">
            <span class="soon-badge">Soon</span>
            <i data-lucide="banknote"></i>
            <span data-pt="Financeiro" data-en="Finance">Financeiro</span>
          </label>
          <label class="mod-card" onclick="toggleMod(this)">
            <input type="checkbox" name="mod" value="calendar">
            <span class="soon-badge">Soon</span>
            <i data-lucide="calendar-check"></i>
            <span data-pt="Agenda" data-en="Calendar">Agenda</span>
          </label>
          <label class="mod-card" onclick="toggleMod(this)">
            <input type="checkbox" name="mod" value="services">
            <span class="soon-badge">Soon</span>
            <i data-lucide="wrench"></i>
            <span data-pt="Serviços" data-en="Services">Serviços</span>
          </label>
          <label class="mod-card" onclick="toggleMod(this)">
            <input type="checkbox" name="mod" value="vehicles">
            <span class="soon-badge">Soon</span>
            <i data-lucide="car"></i>
            <span data-pt="Veículos" data-en="Vehicles">Veículos</span>
          </label>
          <label class="mod-card" onclick="toggleMod(this)">
            <input type="checkbox" name="mod" value="reports">
            <span class="soon-badge">Soon</span>
            <i data-lucide="bar-chart-2"></i>
            <span data-pt="Relatórios" data-en="Reports">Relatórios</span>
          </label>
        </div>
      </div>

      <div class="field">
        <div class="wiz-section-label" data-pt="Controle de acesso" data-en="Access control">Controle de acesso</div>
        <div class="opt-grid">
          <label class="opt-card sel" onclick="selectOpt(this,'acesso')">
            <input type="radio" name="acesso" value="login" checked>
            <span class="opt-icon"><i data-lucide="lock"></i></span>
            <span>
              <strong data-pt="Login obrigatório" data-en="Mandatory login">Login obrigatório</strong>
              <small data-pt="Todos os usuários precisam de conta" data-en="All users need an account">Todos os usuários precisam de conta</small>
            </span>
          </label>
          <label class="opt-card" onclick="selectOpt(this,'acesso')">
            <input type="radio" name="acesso" value="public">
            <span class="opt-icon"><i data-lucide="globe"></i></span>
            <span>
              <strong data-pt="Público" data-en="Public">Público</strong>
              <small data-pt="Sem login, acesso aberto" data-en="No login, open access">Sem login, acesso aberto</small>
            </span>
          </label>
          <label class="opt-card" onclick="selectOpt(this,'acesso')">
            <input type="radio" name="acesso" value="mixed">
            <span class="opt-icon"><i data-lucide="shield-half"></i></span>
            <span>
              <strong data-pt="Misto" data-en="Mixed">Misto</strong>
              <small data-pt="Parte pública + parte protegida" data-en="Public part + protected part">Parte pública + parte protegida</small>
            </span>
          </label>
        </div>
      </div>

      <div class="field">
        <div class="wiz-section-label" data-pt="Modelo de usuários" data-en="User model">Modelo de usuários</div>
        <div class="opt-grid">
          <label class="opt-card sel" onclick="selectOpt(this,'usuarios')">
            <input type="radio" name="usuarios" value="multiple" checked>
            <span class="opt-icon"><i data-lucide="users-2"></i></span>
            <span>
              <strong data-pt="Múltiplos usuários" data-en="Multiple users">Múltiplos usuários</strong>
              <small data-pt="Cada pessoa tem seu próprio login" data-en="Each person has their own login">Cada pessoa tem seu próprio login</small>
            </span>
          </label>
          <label class="opt-card" onclick="selectOpt(this,'usuarios')">
            <input type="radio" name="usuarios" value="single">
            <span class="opt-icon"><i data-lucide="user-check"></i></span>
            <span>
              <strong data-pt="Admin único" data-en="Single admin">Admin único</strong>
              <small data-pt="Somente um administrador" data-en="Single administrator only">Somente um administrador</small>
            </span>
          </label>
        </div>
      </div>

      <div class="field">
        <div class="wiz-section-label" data-pt="Dados isolados entre usuários?" data-en="Isolated data between users?">Dados isolados entre usuários?</div>
        <div class="toggle-row">
          <label class="toggle-opt sel" onclick="selectOpt(this,'isolamento')">
            <input type="radio" name="isolamento" value="yes" checked>
            <i data-lucide="lock-keyhole" style="width:15px;height:15px;"></i>
            <span data-pt="Sim, isolado" data-en="Yes, isolated">Sim, isolado</span>
          </label>
          <label class="toggle-opt" onclick="selectOpt(this,'isolamento')">
            <input type="radio" name="isolamento" value="no">
            <i data-lucide="share-2" style="width:15px;height:15px;"></i>
            <span data-pt="Não, compartilhado" data-en="No, shared">Não, compartilhado</span>
          </label>
        </div>
      </div>
    </div>

    <!-- STEP 4: Context + GitHub -->
    <div id="form-context" style="display:none;">
      <div class="field">
        <label style="display:flex;align-items:center;gap:.4rem;">
          <i data-lucide="github" style="width:15px;height:15px;"></i>
          <span data-pt="Repositório GitHub" data-en="GitHub Repository">Repositório GitHub</span>
          <small style="color:var(--muted);font-weight:400;" data-pt="(opcional)" data-en="(optional)">(opcional)</small>
        </label>
        <?php if ($_hasGithubToken): ?>
          <div style="display:flex;gap:.4rem;align-items:center;">
            <select id="app-github"
              style="flex:1;padding:.45rem .65rem;border:1px solid var(--rule);border-radius:8px;
                     font-family:'Geist Mono',monospace;font-size:.78rem;background:var(--paper);
                     color:var(--ink);">
              <option value="">— <?= $_ghOwner ? htmlspecialchars($_ghOwner).'/' : '' ?>...</option>
            </select>
            <button type="button" onclick="loadGhRepos('app-github')" title="Refresh list"
              style="padding:.4rem .55rem;border:1px solid var(--rule);border-radius:7px;
                     background:var(--paper);cursor:pointer;color:var(--muted);flex-shrink:0;">
              <i data-lucide="refresh-cw" style="width:13px;height:13px;"></i>
            </button>
          </div>
          <small data-pt="Selecione ou aguarde carregar a lista dos seus repositórios."
                 data-en="Select from your repositories list.">
            Selecione ou aguarde carregar a lista dos seus repositórios.
          </small>
        <?php else: ?>
          <input type="text" id="app-github"
            placeholder="<?= $_ghOwner ? htmlspecialchars($_ghOwner).'/nome-do-repo' : 'owner/nome-do-repo' ?>"
            style="font-family:'Geist Mono',monospace;font-size:.8rem;">
          <small>
            <a href="settings.php" style="color:var(--warm);"
               data-pt="Conecte o GitHub em Settings" data-en="Connect GitHub in Settings">
              Conecte o GitHub em Settings
            </a>
            <span data-pt=" para selecionar da lista." data-en=" to select from a list."> para selecionar da lista.</span>
          </small>
        <?php endif; ?>
      </div>

      <div class="field">
        <label style="display:flex;align-items:center;gap:.4rem;">
          <i data-lucide="bot" style="width:15px;height:15px;stroke:var(--warm);"></i>
          <span data-pt="Contexto para o Claude" data-en="Context for Claude">Contexto para o Claude</span>
          <span style="font-size:.72rem;font-weight:400;color:var(--muted);background:var(--cream);padding:.1rem .45rem;border-radius:4px;"
                data-pt="obrigatório" data-en="required">obrigatório</span>
        </label>
        <small style="margin-bottom:.5rem;display:block;"
               data-pt="Descreva o sistema com detalhes. Claude lê isso antes de começar."
               data-en="Describe the system in detail. Claude reads this before starting.">
          Descreva o sistema com detalhes. Claude lê isso antes de começar.
        </small>
        <textarea id="app-brief" rows="11" spellcheck="false"
          style="width:100%;padding:.75rem;border:1px solid var(--rule);border-radius:8px;font-family:'Geist Mono',monospace;font-size:.78rem;line-height:1.7;resize:vertical;background:var(--paper);color:var(--ink);border-color:var(--warm);box-shadow:0 0 0 3px rgba(217,99,58,.08);">
        </textarea>
      </div>
    </div>

    <!-- Publish / Provision / Edit forms -->
    <div id="form-publish"   style="display:none;"><p id="publish-msg"   style="color:var(--muted);font-size:.875rem;"></p></div>
    <div id="form-provision" style="display:none;"><p id="provision-msg" style="color:var(--muted);font-size:.875rem;margin-bottom:1rem;"></p></div>

    <div id="form-edit" style="display:none;">
      <div style="background:var(--cream);border-radius:8px;padding:.6rem .875rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;">
        <i data-lucide="info" style="width:14px;height:14px;stroke:var(--muted);flex-shrink:0;"></i>
        <span style="font-size:.78rem;color:var(--muted);"
              data-pt="O nome do app não pode ser alterado após a criação."
              data-en="The app name cannot be changed after creation.">O nome do app não pode ser alterado após a criação.</span>
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:.4rem;">
          <i data-lucide="github" style="width:14px;height:14px;"></i>
          GitHub Repository
        </label>
        <?php if ($_hasGithubToken): ?>
          <div style="display:flex;gap:.4rem;align-items:center;">
            <select id="edit-github"
              style="flex:1;padding:.45rem .65rem;border:1px solid var(--rule);border-radius:7px;
                     font-family:'Geist Mono',monospace;font-size:.78rem;background:var(--paper);
                     color:var(--ink);">
              <option value="">— selecionar —</option>
            </select>
            <button type="button" onclick="loadGhRepos('edit-github')" title="Refresh list"
              style="padding:.4rem .55rem;border:1px solid var(--rule);border-radius:7px;
                     background:var(--paper);cursor:pointer;color:var(--muted);flex-shrink:0;">
              <i data-lucide="refresh-cw" style="width:13px;height:13px;"></i>
            </button>
          </div>
        <?php else: ?>
          <input type="text" id="edit-github"
            placeholder="owner/nome-do-repo"
            style="width:100%;padding:.5rem .75rem;border:1px solid var(--rule);
                   border-radius:6px;font-family:'Geist Mono',monospace;font-size:.8rem;">
        <?php endif; ?>
      </div>
      <div class="field">
        <label style="display:flex;align-items:center;gap:.4rem;">
          <i data-lucide="bot" style="width:14px;height:14px;stroke:var(--warm);"></i>
          <span data-pt="Instruções para o Claude" data-en="Instructions for Claude">Instruções para o Claude</span>
        </label>
        <small style="margin-bottom:.4rem;display:block;"
               data-pt="Contexto, regras de negócio e decisões técnicas."
               data-en="Context, business rules and technical decisions.">Contexto, regras de negócio e decisões técnicas.</small>
        <textarea id="edit-notes" rows="7"
          data-pt-placeholder="Ex: Usar UUID como PK. Relatórios em PDF. Cliente prefere layout minimalista..."
          data-en-placeholder="E.g.: Use UUID as PK. PDF reports. Client prefers minimal layout..."
          placeholder="Ex: Usar UUID como PK. Relatórios em PDF. Cliente prefere layout minimalista..."
          style="width:100%;padding:.75rem;border:1px solid var(--rule);border-radius:8px;font-family:'Geist Mono',monospace;font-size:.78rem;line-height:1.7;resize:vertical;background:var(--paper);color:var(--ink);"></textarea>
      </div>
      <div id="ssh-section" style="display:none;margin-top:1rem;padding:1rem;background:var(--cream);border-radius:8px;border:1px solid var(--rule);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
          <label style="font-size:.8rem;font-weight:600;">Deploy Key (SSH)</label>
          <div style="display:flex;gap:.5rem;">
            <button type="button" class="btn btn-secondary btn-xs" onclick="testSshConnection()"
                    data-pt="Testar" data-en="Test">Testar</button>
            <button type="button" class="btn btn-secondary btn-xs" onclick="gitPush()">Git push</button>
          </div>
        </div>
        <textarea id="ssh-pubkey" readonly rows="3"
          style="width:100%;padding:.5rem .75rem;border:1px solid var(--rule);border-radius:6px;font-family:monospace;font-size:.72rem;background:#fff;resize:none;"></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.5rem;">
          <small style="color:var(--muted);">GitHub → Settings → Deploy Keys → Add key (write access)</small>
          <button type="button" class="btn btn-secondary btn-xs" onclick="copyKey()"
                  data-pt="Copiar" data-en="Copy">Copiar</button>
        </div>
        <div id="ssh-status" style="margin-top:.5rem;font-size:.8rem;"></div>
      </div>
    </div>

    <!-- Output log -->
    <div id="modal-output" style="display:none;margin-top:1rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.35rem;">
        <span style="font-size:.78rem;color:var(--muted);">Output</span>
        <button type="button" onclick="copyLog()" class="btn btn-secondary btn-xs"
                data-pt="Copiar" data-en="Copy">Copiar</button>
      </div>
      <pre id="modal-log" style="background:var(--ink);color:#c9d1d9;padding:1rem;border-radius:8px;font-family:monospace;font-size:.78rem;height:260px;overflow-y:auto;white-space:pre-wrap;margin:0;"></pre>
    </div>

    <!-- Creation progress -->
    <div id="modal-progress" style="display:none;">
      <div class="prog-spinner" id="prog-spinner">
        <i data-lucide="loader-2" style="width:32px;height:32px;stroke:var(--warm);"></i>
        <span id="prog-spinner-label" data-pt="Criando app..." data-en="Creating app...">Criando app...</span>
      </div>
      <div id="progress-items" style="display:none;"></div>
      <div id="prog-success" style="display:none;" class="prog-success">
        <div class="check-circle"><i data-lucide="check" style="width:26px;height:26px;"></i></div>
        <strong data-pt="App criado com sucesso!" data-en="App created successfully!">App criado com sucesso!</strong>
        <a id="prog-app-url" href="#" target="_blank" class="prog-url">
          <i data-lucide="external-link" style="width:13px;height:13px;"></i>
          <span id="prog-app-url-label"></span>
        </a>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal()"
              data-pt="Cancelar" data-en="Cancel">Cancelar</button>
      <button class="btn btn-secondary" id="btn-back" style="display:none;" onclick="backStep()"
              data-pt="← Voltar" data-en="← Back">← Voltar</button>
      <button class="btn btn-primary" id="btn-action" onclick="runAction()"
              data-pt="Próximo →" data-en="Next →">Próximo →</button>
    </div>
  </div>
</div>

<script src="assets/js/studio.js"></script>
<script>
const _appsData       = <?= $appsJson ?>;
const _hasGithubToken = <?= $_hasGithubToken ? 'true' : 'false' ?>;
const _ghOwner        = '<?= addslashes($_ghOwner) ?>';
let _mode = 'new', _app = '', _to = '', _step = 1;
let _ghRepos = []; // cached repo list

// Translation helper
function t(pt, en) { return studioLang() === 'en' ? en : pt; }

// Wizard language selection
function selectLangOpt(card, lang) {
  document.querySelectorAll('#lang-opts .lang-opt-card').forEach(c => c.classList.remove('sel'));
  card.classList.add('sel');
  card.querySelector('input').checked = true;
  applyModuleLang(lang);
}

// Enable/disable modules based on template language
function applyModuleLang(lang) {
  const notice = document.getElementById('mod-en-notice');
  if (notice) notice.style.display = lang === 'en' ? '' : 'none';

  document.querySelectorAll('.mod-card').forEach(function(card) {
    const val = card.querySelector('input').value;
    const disabled = lang === 'en' && val !== 'dashboard';
    card.classList.toggle('mod-card--disabled', disabled);
    if (disabled) {
      card.querySelector('input').checked = false;
      card.querySelector('input').disabled = true;
      card.classList.remove('sel');
    } else {
      card.querySelector('input').disabled = false;
      if (val === 'dashboard') {
        card.querySelector('input').checked = true;
        card.classList.add('sel');
      }
    }
  });
}

function getSelectedLang() {
  const radio = document.querySelector('#lang-opts input[name="app-lang"]:checked');
  return radio ? radio.value : 'pt';
}

function openModal(mode, app = '', to = '') {
  _mode = mode; _app = app; _to = to;
  document.getElementById('modal').classList.add('open');
  document.getElementById('modal-output').style.display = 'none';
  document.getElementById('modal-log').textContent = '';
  document.getElementById('btn-action').disabled = false;
  ['form-lang','form-new','form-wizard','form-context','form-publish','form-provision','form-edit'].forEach(id =>
    document.getElementById(id).style.display = 'none'
  );
  document.getElementById('modal-progress').style.display = 'none';
  document.getElementById('btn-back').style.display = 'none';
  document.getElementById('wiz-progress').style.display = 'none';

  if (mode === 'new') {
    document.getElementById('modal-title').textContent = t('Novo app', 'New app');
    document.getElementById('form-lang').style.display = '';
    document.getElementById('btn-action').textContent = t('Próximo →', 'Next →');
    document.getElementById('wiz-progress').style.display = '';
    // Pre-select language matching current Studio language
    const stLang = studioLang();
    document.querySelectorAll('#lang-opts .lang-opt-card').forEach(c => {
      const val = c.querySelector('input').value;
      c.classList.toggle('sel', val === stLang);
      c.querySelector('input').checked = val === stLang;
    });
    applyModuleLang(stLang);
    setWizStep(1);

  } else if (mode === 'edit') {
    const meta = _appsData[app] || {};
    document.getElementById('modal-title').textContent = t('Editar — ', 'Edit — ') + app;
    document.getElementById('form-edit').style.display = '';
    document.getElementById('edit-notes').value   = meta.memory_notes || '';
    document.getElementById('btn-action').textContent = t('Salvar', 'Save');
    setGhRepoSelect('edit-github', meta.github_repo || '');
    loadSshKey(app);

  } else if (mode === 'provision') {
    document.getElementById('modal-title').textContent = t('Provisionar ', 'Provision ') + app;
    document.getElementById('form-provision').style.display = '';
    document.getElementById('provision-msg').textContent =
      t(`Criar ambiente DEV para "${app}"? Isso cria pasta, banco, terminal e repositório.`,
        `Create DEV environment for "${app}"? This creates the folder, database, terminal and repository.`);
    document.getElementById('btn-action').textContent = t('⚡ Provisionar DEV', '⚡ Provision DEV');

  } else if (mode === 'publish') {
    const envLabel = to === 'hml' ? t('Homologação', 'Staging') : t('Produção', 'Production');
    document.getElementById('modal-title').textContent = t('Publicar em ', 'Publish to ') + envLabel;
    document.getElementById('form-publish').style.display = '';
    document.getElementById('publish-msg').textContent =
      t(`Copiar código de DEV para ${envLabel}? Um novo banco isolado será criado.`,
        `Copy code from DEV to ${envLabel}? A new isolated database will be created.`);
    document.getElementById('btn-action').textContent = t(`🚀 Publicar em ${envLabel}`, `🚀 Deploy to ${envLabel}`);
  }
}

// Progress item map — regexes match EN output from bin/newapp
const _piMap = [
  { re: /✓ Directory/,    icon: 'folder',        label: () => t('Diretório criado',       'App directory created') },
  { re: /✓ System user/,  icon: 'user',           label: () => t('Usuário isolado',         'Isolated system user') },
  { re: /✓ Database/,     icon: 'database',       label: () => t('Banco configurado',       'Database configured') },
  { re: /✓ Tables/,       icon: 'table-2',        label: () => t('Tabelas criadas',         'Database tables created') },
  { re: /✓ Terminal/,     icon: 'terminal',       label: () => t('Terminal configurado',    'Web terminal configured') },
  { re: /✓ \.env/,        icon: 'file-cog',       label: () => t('Variáveis configuradas',  'Env variables set') },
  { re: /✓ Boilerplate/,  icon: 'layers',         label: () => t('Código instalado',        'Boilerplate installed') },
  { re: /✓ Memory/,       icon: 'brain-circuit',  label: () => t('Contexto inicializado',   'Claude context initialized') },
  { re: /✓ Git/,          icon: 'git-branch',     label: () => t('Git inicializado',        'Git initialized') },
  { re: /DNS/,            icon: 'globe',          label: () => t('DNS configurado',         'DNS configured') },
  { re: /✓ Permissions/,  icon: 'shield-check',   label: () => t('Permissões ajustadas',    'Permissions set') },
];

function animateProgress(output, appName, success) {
  const lines = output.split('\n');
  const items = [];
  for (const line of lines) {
    for (const map of _piMap) {
      if (map.re.test(line) && !items.find(i => i === map)) {
        items.push(map); break;
      }
    }
  }

  const urlMatch = output.match(/URL:\s+(https?:\/\/\S+)/);
  const appUrl = urlMatch ? urlMatch[1] : '';

  const container = document.getElementById('progress-items');
  container.innerHTML = '';
  document.getElementById('prog-spinner').style.display = 'none';
  container.style.display = 'block';

  items.forEach((item, i) => {
    setTimeout(() => {
      const el = document.createElement('div');
      el.className = 'pi-item';
      el.innerHTML =
        `<span class="pi-icon"><i data-lucide="${item.icon}"></i></span>` +
        `<span class="pi-label">${item.label()}</span>` +
        `<span class="pi-check"><i data-lucide="check-circle-2"></i></span>`;
      container.appendChild(el);
      lucide.createIcons();

      if (i === items.length - 1) {
        setTimeout(() => {
          if (success) {
            document.getElementById('prog-success').style.display = 'block';
            if (appUrl) {
              document.getElementById('prog-app-url').href = appUrl;
              document.getElementById('prog-app-url-label').textContent = appUrl;
            } else {
              document.getElementById('prog-app-url').style.display = 'none';
            }
            lucide.createIcons();
          }
          document.getElementById('btn-back').style.display = 'none';
          const btn = document.getElementById('btn-action');
          btn.textContent = t('Abrir workspace →', 'Open workspace →');
          btn.disabled = false;
          btn.onclick = () => { window.location.href = 'workspace.php?app=' + encodeURIComponent(appName); };
          const cancel = document.querySelector('.modal-footer .btn-secondary');
          cancel.textContent = t('Ficar no dashboard', 'Stay on dashboard');
          cancel.onclick = () => location.reload();
        }, 400);
      }
    }, i * 150);
  });
}

function closeModal() {
  document.getElementById('modal').classList.remove('open');
  document.getElementById('wiz-progress').style.display = 'none';
}

function setWizStep(n) {
  _step = n;
  const cls = (step, cur) => 'wiz-step ' + (cur > step ? 'done' : cur === step ? 'active' : '');
  document.getElementById('wp-s1').className = cls(1, n);
  document.getElementById('wp-s2').className = cls(2, n);
  document.getElementById('wp-s3').className = cls(3, n);
  document.getElementById('wp-s4').className = cls(4, n);
  document.getElementById('wp-line1').className = 'wiz-connector' + (n > 1 ? ' done' : '');
  document.getElementById('wp-line2').className = 'wiz-connector' + (n > 2 ? ' done' : '');
  document.getElementById('wp-line3').className = 'wiz-connector' + (n > 3 ? ' done' : '');
}

function backStep() {
  if (_mode !== 'new') return;
  if (_step === 2) {
    document.getElementById('form-new').style.display = 'none';
    document.getElementById('form-lang').style.display = '';
    document.getElementById('modal-title').textContent = t('Novo app', 'New app');
    document.getElementById('btn-action').textContent = t('Próximo →', 'Next →');
    document.getElementById('btn-back').style.display = 'none';
    setWizStep(1);
  } else if (_step === 3) {
    document.getElementById('form-wizard').style.display = 'none';
    document.getElementById('form-new').style.display = '';
    document.getElementById('btn-action').textContent = t('Próximo →', 'Next →');
    setWizStep(2);
  } else if (_step === 4) {
    document.getElementById('form-context').style.display = 'none';
    document.getElementById('form-wizard').style.display = '';
    document.getElementById('btn-action').textContent = t('Próximo →', 'Next →');
    setWizStep(3);
  }
  setTimeout(() => lucide.createIcons(), 30);
}

function toggleMod(card) {
  if (card.classList.contains('mod-card--disabled')) return;
  const cb = card.querySelector('input');
  cb.checked = !cb.checked;
  card.classList.toggle('sel', cb.checked);
}

function selectOpt(card, name) {
  document.querySelectorAll(`#form-wizard [name="${name}"]`).forEach(inp => {
    inp.closest('.opt-card, .toggle-opt').classList.remove('sel');
  });
  card.querySelector('input').checked = true;
  card.classList.add('sel');
}

async function runAction() {
  const btn = document.getElementById('btn-action');
  const out = document.getElementById('modal-output');
  const log = document.getElementById('modal-log');
  btn.disabled = true;
  out.style.display = 'block';
  log.textContent = t('Processando...', 'Processing...') + '\n';

  let endpoint, body;

  if (_mode === 'new') {
    const name = document.getElementById('app-name').value.trim();
    const lang = getSelectedLang();

    if (_step === 1) {
      // Language selected → go to name
      out.style.display = 'none';
      document.getElementById('form-lang').style.display = 'none';
      document.getElementById('form-new').style.display = '';
      document.getElementById('modal-title').textContent = t('Novo app', 'New app');
      document.getElementById('btn-action').textContent = t('Próximo →', 'Next →');
      document.getElementById('btn-back').style.display = '';
      btn.disabled = false;
      setWizStep(2);
      setTimeout(() => { document.getElementById('app-name').focus(); lucide.createIcons(); }, 50);
      return;
    }

    if (_step === 2) {
      if (!name) { alert(t('Informe o nome do app', 'Enter the app name')); btn.disabled = false; out.style.display = 'none'; return; }
      if (!/^[a-z0-9][a-z0-9-]*$/.test(name)) { alert(t('Use apenas letras minúsculas, números e hífens', 'Use only lowercase letters, numbers and hyphens')); btn.disabled = false; out.style.display = 'none'; return; }
      out.style.display = 'none';
      document.getElementById('form-new').style.display = 'none';
      document.getElementById('form-wizard').style.display = '';
      document.getElementById('modal-title').textContent = t('Módulos — ', 'Modules — ') + name;
      document.getElementById('btn-action').textContent = t('Próximo →', 'Next →');
      btn.disabled = false;
      setWizStep(3);
      applyModuleLang(lang);
      setTimeout(() => lucide.createIcons(), 30);
      return;
    }

    if (_step === 3) {
      out.style.display = 'none';
      document.getElementById('form-wizard').style.display = 'none';
      document.getElementById('form-context').style.display = '';
      document.getElementById('modal-title').textContent = t('Contexto — ', 'Context — ') + name;
      maybeLoadReposForWizard();
      document.getElementById('btn-action').textContent = t('Criar app →', 'Create app →');
      // Populate textarea template based on language
      const ta = document.getElementById('app-brief');
      if (!ta.value.trim()) {
        ta.value = lang === 'en'
          ? '## What this system should do\nDescribe the main goal and the problem it solves.\n\n## Target users\nWho will use it? E.g.: sales team, clinic staff, fleet managers.\n\n## Essential features\nList in priority order:\n-\n-\n-\n\n## Business rules\n-\n\n## Out of scope\n-'
          : '## O que este sistema deve fazer\nDescreva o objetivo principal e o problema que resolve.\n\n## Público-alvo\nQuem vai usar? Ex: vendedores, médicos, gestores.\n\n## Funcionalidades essenciais\nListe em ordem de prioridade:\n-\n-\n-\n\n## Regras de negócio\n-\n\n## Fora do escopo\n-';
      }
      btn.disabled = false;
      setWizStep(4);
      setTimeout(() => lucide.createIcons(), 30);
      return;
    }

    // Step 4 → register + provision
    const brief = document.getElementById('app-brief').value.trim();
    if (!brief || brief.length < 30) {
      alert(t('Descreva o contexto do sistema antes de continuar.', 'Describe the system context before continuing.'));
      btn.disabled = false; out.style.display = 'none'; return;
    }
    const mods      = [...document.querySelectorAll('#form-wizard input[name="mod"]:checked')].map(e => e.value);
    const acesso    = document.querySelector('#form-wizard input[name="acesso"]:checked')?.value    || 'login';
    const usuarios  = document.querySelector('#form-wizard input[name="usuarios"]:checked')?.value  || 'multiple';
    const isolamento = document.querySelector('#form-wizard input[name="isolamento"]:checked')?.value || 'yes';

    out.style.display = 'none';
    document.getElementById('modal-progress').style.display = 'block';
    document.getElementById('prog-spinner').style.display = 'flex';
    document.getElementById('progress-items').style.display = 'none';
    document.getElementById('prog-success').style.display = 'none';
    document.getElementById('prog-spinner-label').textContent = t('Registrando app...', 'Registering app...');
    btn.textContent = t('Criando...', 'Creating...');
    setTimeout(() => lucide.createIcons(), 30);

    try {
      const r1 = await fetch('api/newapp.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          name, description: name, language: lang,
          github_repo: document.getElementById('app-github').value.trim(),
          config: { modules: mods, access: acesso, users: usuarios, isolation: isolamento, context: brief }
        })
      });
      if (r1.status === 401) { location.href = 'login.php'; return; }
      const d1 = await r1.json();
      if (!d1.success) {
        document.getElementById('prog-spinner-label').textContent = t('Erro: ', 'Error: ') + (d1.error || t('falha ao registrar.', 'registration failed.'));
        btn.disabled = false; btn.textContent = t('Tentar novamente', 'Try again'); return;
      }

      document.getElementById('prog-spinner-label').textContent = t('Provisionando ambiente DEV...', 'Provisioning DEV environment...');

      const r2 = await fetch('api/provision.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ name })
      });
      if (r2.status === 401) { location.href = 'login.php'; return; }
      const d2 = await r2.json();

      // If a repo was selected, set the git remote automatically after provisioning
      const ghRepo = document.getElementById('app-github').value.trim();
      if (d2.success && ghRepo && _hasGithubToken) {
        document.getElementById('prog-spinner-label').textContent = t('Configurando GitHub...', 'Configuring GitHub...');
        try {
          await fetch('api/git.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({name, action: 'set-remote', repo: ghRepo})
          });
        } catch(e) { /* non-fatal */ }
      }

      animateProgress(d2.output || '', name, d2.success);

    } catch(e) {
      document.getElementById('prog-spinner-label').textContent = t('Erro: ', 'Error: ') + e.message;
      btn.disabled = false; btn.textContent = t('Tentar novamente', 'Try again');
    }
    return;

  } else if (_mode === 'edit') {
    endpoint = 'api/update-app.php';
    body = { name: _app, github_repo: document.getElementById('edit-github').value.trim(), memory_notes: document.getElementById('edit-notes').value.trim() };
    btn.textContent = t('Salvando...', 'Saving...');
  } else if (_mode === 'provision') {
    endpoint = 'api/provision.php';
    body = { name: _app };
    btn.textContent = t('Provisionando...', 'Provisioning...');
  } else if (_mode === 'publish') {
    endpoint = 'api/promote.php';
    body = { name: _app, to: _to };
    btn.textContent = t(`Publicando em ${_to.toUpperCase()}...`, `Deploying to ${_to.toUpperCase()}...`);
  }

  try {
    const resp = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    if (resp.status === 401) { location.href = 'login.php'; return; }
    const data = await resp.json();
    log.textContent = data.output || data.error || data.message || t('Concluído.', 'Done.');
    if (data.success) {
      btn.textContent = t('✓ Concluído!', '✓ Done!');
      setTimeout(() => location.reload(), 2000);
    } else {
      btn.disabled = false;
      btn.textContent = t('Tentar novamente', 'Try again');
    }
  } catch(e) {
    log.textContent = t('Erro: ', 'Error: ') + e.message;
    btn.disabled = false;
    btn.textContent = t('Tentar novamente', 'Try again');
  }
}

document.getElementById('modal').addEventListener('click', e => {
  if (e.target === document.getElementById('modal')) closeModal();
});

// ── GitHub repo list ────────────────────────────────────────────────────────
async function loadGhRepos(selectId) {
  const sel = document.getElementById(selectId);
  if (!sel || !_hasGithubToken) return;

  const prevVal = sel.value;
  sel.disabled = true;

  // Use cache if already loaded
  if (_ghRepos.length === 0) {
    try {
      const r = await fetch('api/github.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'list-repos'})
      });
      const d = await r.json();
      _ghRepos = d.repos || [];
    } catch(e) { _ghRepos = []; }
  }

  // Populate select
  sel.innerHTML = '<option value="">— ' + (_ghOwner ? _ghOwner + '/' : '') + t('selecionar', 'select') + ' —</option>';
  _ghRepos.forEach(repo => {
    const opt = document.createElement('option');
    opt.value = repo.full;   // e.g. "owner/repo-name"
    opt.textContent = repo.name + (repo.private ? ' 🔒' : '');
    if (repo.full === prevVal) opt.selected = true;
    sel.appendChild(opt);
  });

  if (_ghRepos.length === 0) {
    sel.innerHTML += '<option disabled>' + t('Nenhum repo encontrado', 'No repos found') + '</option>';
  }

  sel.disabled = false;
}

// Normalize any GitHub URL/SSH format → "owner/repo"
function ghNormalizeRepo(raw) {
  if (!raw) return '';
  // SSH alias: git@github-app:owner/repo.git or git@github.com:owner/repo.git
  let m = raw.match(/github[^:]*:([^/\s]+\/[^\s]+?)(?:\.git)?$/i);
  if (m) return m[1];
  // HTTPS: https://github.com/owner/repo or https://token@github.com/owner/repo
  m = raw.match(/github\.com\/([^/\s]+\/[^\s.]+?)(?:\.git)?$/i);
  if (m) return m[1];
  // Already "owner/repo"
  if (raw.includes('/')) return raw.replace(/\.git$/, '');
  return raw;
}

// Open a select + pre-select the current value (loads list if needed)
async function setGhRepoSelect(selectId, rawValue) {
  const el = document.getElementById(selectId);
  if (!el) return;
  if (el.tagName === 'INPUT') { el.value = rawValue; return; }

  await loadGhRepos(selectId);

  const normalized = ghNormalizeRepo(rawValue);
  if (normalized) {
    // Try to select matching option
    for (const opt of el.options) {
      if (opt.value === normalized) { el.value = normalized; return; }
    }
    // Not found — add it so the current value is preserved
    const opt = document.createElement('option');
    opt.value = normalized;
    opt.textContent = normalized;
    opt.selected = true;
    el.appendChild(opt);
  }
}

// Auto-load repos when step 4 becomes visible
function maybeLoadReposForWizard() {
  if (_hasGithubToken) loadGhRepos('app-github');
}

async function loadSshKey(name) {
  const section = document.getElementById('ssh-section');
  section.style.display = 'none';
  document.getElementById('ssh-status').textContent = '';
  const resp = await fetch('api/ssh-key.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({name, action:'get'}) });
  const data = await resp.json();
  if (data.public_key) { document.getElementById('ssh-pubkey').value = data.public_key; section.style.display = 'block'; }
}

async function testSshConnection() {
  const status = document.getElementById('ssh-status');
  status.textContent = t('Testando...', 'Testing...');
  const resp = await fetch('api/ssh-key.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({name: _app, action:'test'}) });
  const data = await resp.json();
  status.innerHTML = data.success ? `<span style="color:#2e7d32;">✓ ${t('Conectado!','Connected!')}</span>` : `<span style="color:#c62828;">✗ ${data.output}</span>`;
}

async function gitPush() {
  const status = document.getElementById('ssh-status');
  status.textContent = t('Enviando...', 'Pushing...');
  const resp = await fetch('api/ssh-key.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({name: _app, action:'push'}) });
  const data = await resp.json();
  status.innerHTML = data.success ? `<span style="color:#2e7d32;">✓ ${t('Push feito!','Pushed!')}</span>` : `<span style="color:#c62828;">✗ ${data.output}</span>`;
}

function copyKey() {
  const key = document.getElementById('ssh-pubkey').value;
  const done = () => { document.getElementById('ssh-status').innerHTML = `<span style="color:#2e7d32;">✓ ${t('Copiado!','Copied!')}</span>`; };
  navigator.clipboard ? navigator.clipboard.writeText(key).then(done) : (fallbackCopy(key), done());
}

function copyLog() {
  const btn = event.currentTarget;
  const label = t('Copiado!', 'Copied!');
  const done = () => { btn.textContent = '✓ ' + label; btn.style.background = 'var(--success)'; btn.style.color = '#fff'; setTimeout(() => { btn.textContent = t('Copiar','Copy'); btn.style.background = ''; btn.style.color = ''; }, 2000); };
  navigator.clipboard ? navigator.clipboard.writeText(document.getElementById('modal-log').textContent).then(done) : (fallbackCopy(document.getElementById('modal-log').textContent), done());
}

function fallbackCopy(text) {
  const ta = Object.assign(document.createElement('textarea'), {value: text, style: 'position:fixed;opacity:0'});
  document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
}

lucide.createIcons();

const _origOpen = openModal;
window.openModal = function(...args) { _origOpen(...args); setTimeout(() => lucide.createIcons(), 50); };
</script>
</body>
</html>
